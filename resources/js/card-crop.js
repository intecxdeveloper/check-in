const DETECT_MAX_EDGE = 360;
const OUTPUT_MAX_EDGE = 1600;
const BORDER = 4;
const MIN_AREA = 0.18;
const MAX_AREA = 0.985;

function sourceSize(source) {
    return {
        width: source.naturalWidth || source.videoWidth || source.width,
        height: source.naturalHeight || source.videoHeight || source.height,
    };
}

/**
 * Otsu threshold over a 0–255 histogram.
 */
function otsu(histogram, total) {
    let sum = 0;
    for (let i = 0; i < 256; i += 1) {
        sum += i * histogram[i];
    }

    let sumBackground = 0;
    let weightBackground = 0;
    let best = 0;
    let threshold = 0;
    for (let i = 0; i < 256; i += 1) {
        weightBackground += histogram[i];
        if (weightBackground === 0) {
            continue;
        }
        const weightForeground = total - weightBackground;
        if (weightForeground === 0) {
            break;
        }
        sumBackground += i * histogram[i];
        const meanBackground = sumBackground / weightBackground;
        const meanForeground = (sum - sumBackground) / weightForeground;
        const between = weightBackground * weightForeground * ((meanBackground - meanForeground) ** 2);
        if (between > best) {
            best = between;
            threshold = i;
        }
    }

    return threshold;
}

function morph(mask, width, height, keepIfAny) {
    const out = new Uint8Array(mask.length);
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            let any = false;
            let all = true;
            for (let dy = -1; dy <= 1; dy += 1) {
                for (let dx = -1; dx <= 1; dx += 1) {
                    const nx = x + dx;
                    const ny = y + dy;
                    const on = nx >= 0 && ny >= 0 && nx < width && ny < height && mask[(ny * width) + nx] === 1;
                    any = any || on;
                    all = all && on;
                }
            }
            out[(y * width) + x] = (keepIfAny ? any : all) ? 1 : 0;
        }
    }

    return out;
}

function largestComponent(mask, width, height) {
    const labels = new Int32Array(mask.length);
    const stack = new Int32Array(mask.length);
    let bestLabel = 0;
    let bestSize = 0;
    let label = 0;

    for (let start = 0; start < mask.length; start += 1) {
        if (mask[start] !== 1 || labels[start] !== 0) {
            continue;
        }
        label += 1;
        let size = 0;
        let top = 0;
        stack[top++] = start;
        labels[start] = label;
        while (top > 0) {
            const index = stack[--top];
            size += 1;
            const x = index % width;
            const neighbours = [
                x > 0 ? index - 1 : -1,
                x < width - 1 ? index + 1 : -1,
                index - width,
                index + width,
            ];
            for (const next of neighbours) {
                if (next >= 0 && next < mask.length && mask[next] === 1 && labels[next] === 0) {
                    labels[next] = label;
                    stack[top++] = next;
                }
            }
        }
        if (size > bestSize) {
            bestSize = size;
            bestLabel = label;
        }
    }

    return { labels, label: bestLabel, size: bestSize };
}

/**
 * Area of the component with interior holes (printed text, logos) filled in:
 * everything not reachable from the image border without crossing the card.
 */
function filledSize(labels, label, width, height) {
    const total = width * height;
    const outside = new Uint8Array(total);
    const stack = new Int32Array(total);
    let top = 0;
    const push = (index) => {
        if (outside[index] === 0 && labels[index] !== label) {
            outside[index] = 1;
            stack[top++] = index;
        }
    };
    for (let x = 0; x < width; x += 1) {
        push(x);
        push(((height - 1) * width) + x);
    }
    for (let y = 0; y < height; y += 1) {
        push(y * width);
        push((y * width) + width - 1);
    }
    let reached = 0;
    while (top > 0) {
        const index = stack[--top];
        reached += 1;
        const x = index % width;
        if (x > 0) {
            push(index - 1);
        }
        if (x < width - 1) {
            push(index + 1);
        }
        if (index >= width) {
            push(index - width);
        }
        if (index + width < total) {
            push(index + width);
        }
    }

    return total - reached;
}

function polygonArea(points) {
    let area = 0;
    points.forEach((point, i) => {
        const next = points[(i + 1) % points.length];
        area += (point.x * next.y) - (next.x * point.y);
    });

    return Math.abs(area) / 2;
}

function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

/**
 * Solves the 3×3 homography that maps the unit output rectangle onto the
 * detected card corners.
 */
function homography(from, to) {
    const rows = [];
    for (let i = 0; i < 4; i += 1) {
        const { x, y } = from[i];
        const { x: u, y: v } = to[i];
        rows.push([x, y, 1, 0, 0, 0, -u * x, -u * y, u]);
        rows.push([0, 0, 0, x, y, 1, -v * x, -v * y, v]);
    }

    for (let col = 0; col < 8; col += 1) {
        let pivot = col;
        for (let row = col + 1; row < 8; row += 1) {
            if (Math.abs(rows[row][col]) > Math.abs(rows[pivot][col])) {
                pivot = row;
            }
        }
        [rows[col], rows[pivot]] = [rows[pivot], rows[col]];
        const lead = rows[col][col];
        if (Math.abs(lead) < 1e-9) {
            return null;
        }
        for (let k = col; k < 9; k += 1) {
            rows[col][k] /= lead;
        }
        for (let row = 0; row < 8; row += 1) {
            if (row !== col) {
                const factor = rows[row][col];
                for (let k = col; k < 9; k += 1) {
                    rows[row][k] -= factor * rows[col][k];
                }
            }
        }
    }

    return [...rows.map((row) => row[8]), 1];
}

function detectCorners(source) {
    const { width: fullWidth, height: fullHeight } = sourceSize(source);
    const scale = Math.min(1, DETECT_MAX_EDGE / Math.max(fullWidth, fullHeight, 1));
    const width = Math.max(8, Math.round(fullWidth * scale));
    const height = Math.max(8, Math.round(fullHeight * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (! context) {
        return null;
    }
    context.drawImage(source, 0, 0, width, height);
    const { data } = context.getImageData(0, 0, width, height);

    let red = 0;
    let green = 0;
    let blue = 0;
    let borderCount = 0;
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            if (x >= BORDER && y >= BORDER && x < width - BORDER && y < height - BORDER) {
                continue;
            }
            const i = ((y * width) + x) * 4;
            red += data[i];
            green += data[i + 1];
            blue += data[i + 2];
            borderCount += 1;
        }
    }
    red /= borderCount;
    green /= borderCount;
    blue /= borderCount;

    const total = width * height;
    const difference = new Uint8Array(total);
    const histogram = new Uint32Array(256);
    for (let p = 0; p < total; p += 1) {
        const i = p * 4;
        const value = Math.min(255, Math.round(Math.hypot(data[i] - red, data[i + 1] - green, data[i + 2] - blue) * 0.7));
        difference[p] = value;
        histogram[value] += 1;
    }

    const threshold = Math.max(18, otsu(histogram, total));
    let mask = new Uint8Array(total);
    for (let p = 0; p < total; p += 1) {
        mask[p] = difference[p] > threshold ? 1 : 0;
    }
    mask = morph(morph(mask, width, height, true), width, height, false);
    mask = morph(morph(mask, width, height, false), width, height, true);

    const { labels, label } = largestComponent(mask, width, height);
    if (label === 0) {
        return null;
    }
    const size = filledSize(labels, label, width, height);
    const coverage = size / total;
    if (coverage < MIN_AREA || coverage > MAX_AREA) {
        return null;
    }

    let topLeft = null;
    let topRight = null;
    let bottomRight = null;
    let bottomLeft = null;
    let minX = width;
    let minY = height;
    let maxX = 0;
    let maxY = 0;
    for (let p = 0; p < total; p += 1) {
        if (labels[p] !== label) {
            continue;
        }
        const x = p % width;
        const y = (p - x) / width;
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x);
        maxY = Math.max(maxY, y);
        if (! topLeft || x + y < topLeft.x + topLeft.y) {
            topLeft = { x, y };
        }
        if (! bottomRight || x + y > bottomRight.x + bottomRight.y) {
            bottomRight = { x, y };
        }
        if (! topRight || x - y > topRight.x - topRight.y) {
            topRight = { x, y };
        }
        if (! bottomLeft || x - y < bottomLeft.x - bottomLeft.y) {
            bottomLeft = { x, y };
        }
    }

    const toFull = (point) => ({ x: (point.x + 0.5) / scale, y: (point.y + 0.5) / scale });
    const quad = [topLeft, topRight, bottomRight, bottomLeft];
    const quadArea = polygonArea(quad);
    const rectangular = quadArea > 0 && size / quadArea > 0.86 && size / quadArea < 1.12;

    if (rectangular) {
        return { corners: quad.map(toFull), warp: true };
    }

    return {
        corners: [
            { x: minX, y: minY },
            { x: maxX + 1, y: minY },
            { x: maxX + 1, y: maxY + 1 },
            { x: minX, y: maxY + 1 },
        ].map((point) => ({ x: point.x / scale, y: point.y / scale })),
        warp: false,
    };
}

/**
 * Finds the business card in a photo and returns a canvas with only the card,
 * straightened if it was shot at an angle. Returns null when the card cannot be
 * told apart from the background, so callers keep the original photo.
 *
 * @param {HTMLImageElement|HTMLCanvasElement} source
 * @returns {HTMLCanvasElement|null}
 */
export function cropCard(source) {
    const found = detectCorners(source);
    if (! found) {
        return null;
    }

    const [topLeft, topRight, bottomRight, bottomLeft] = found.corners;
    const cardWidth = Math.max(distance(topLeft, topRight), distance(bottomLeft, bottomRight));
    const cardHeight = Math.max(distance(topLeft, bottomLeft), distance(topRight, bottomRight));
    const scale = Math.min(1, OUTPUT_MAX_EDGE / Math.max(cardWidth, cardHeight, 1));
    const width = Math.max(1, Math.round(cardWidth * scale));
    const height = Math.max(1, Math.round(cardHeight * scale));
    const output = document.createElement('canvas');
    output.width = width;
    output.height = height;
    const outContext = output.getContext('2d');
    if (! outContext) {
        return null;
    }

    if (! found.warp) {
        outContext.drawImage(source, topLeft.x, topLeft.y, topRight.x - topLeft.x, bottomLeft.y - topLeft.y, 0, 0, width, height);

        return output;
    }

    const { width: fullWidth, height: fullHeight } = sourceSize(source);
    const input = document.createElement('canvas');
    input.width = fullWidth;
    input.height = fullHeight;
    const inContext = input.getContext('2d', { willReadFrequently: true });
    if (! inContext) {
        return null;
    }
    inContext.drawImage(source, 0, 0);
    const src = inContext.getImageData(0, 0, fullWidth, fullHeight).data;

    const matrix = homography(
        [{ x: 0, y: 0 }, { x: width, y: 0 }, { x: width, y: height }, { x: 0, y: height }],
        found.corners,
    );
    if (! matrix) {
        return null;
    }

    const target = outContext.createImageData(width, height);
    const dst = target.data;
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            const w = (matrix[6] * x) + (matrix[7] * y) + matrix[8];
            const sx = ((matrix[0] * x) + (matrix[1] * y) + matrix[2]) / w;
            const sy = ((matrix[3] * x) + (matrix[4] * y) + matrix[5]) / w;
            const ix = Math.min(fullWidth - 1, Math.max(0, Math.round(sx)));
            const iy = Math.min(fullHeight - 1, Math.max(0, Math.round(sy)));
            const from = ((iy * fullWidth) + ix) * 4;
            const to = ((y * width) + x) * 4;
            dst[to] = src[from];
            dst[to + 1] = src[from + 1];
            dst[to + 2] = src[from + 2];
            dst[to + 3] = 255;
        }
    }
    outContext.putImageData(target, 0, 0);

    return output;
}
