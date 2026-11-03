<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExpoSeeder extends Seeder
{
    public function run(): void
    {
        // Demo staff logins and sample visitors are for local/demo use only.
        // In production create real staff with `php artisan expo:staff`.
        if (app()->isProduction()) {
            $this->command?->warn('Skipping demo staff and sample visitors in production. Use `php artisan expo:staff` to add staff.');
            $this->seedServices();

            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'staff@expo.test'],
            [
                'name' => 'NADI Admin',
                'password' => Hash::make('password'),
            ]
        );

        $services = $this->seedServices();

        $this->seedSamples($services);
    }

    /**
     * @return Collection<string, Service>
     */
    private function seedServices()
    {
        return collect([
            ['name' => 'GIS / Geospatial', 'slug' => 'gis', 'color' => '#38bdf8'],
            ['name' => 'Land Management', 'slug' => 'land', 'color' => '#34d399'],
            ['name' => 'Software / IT', 'slug' => 'software', 'color' => '#a78bfa'],
            ['name' => 'Data Analytics', 'slug' => 'analytics', 'color' => '#fbbf24'],
            ['name' => 'System Integration', 'slug' => 'integration', 'color' => '#fb7185'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#94a3b8'],
        ])->mapWithKeys(function (array $s) {
            $service = Service::query()->updateOrCreate(['slug' => $s['slug']], $s);

            return [$service->slug => $service];
        });
    }

    private function seedSamples($services): void
    {
        // Replace prior demo map data so re-seeding stays at a clean 100 visitors.
        Lead::query()->delete();
        Visitor::query()->delete();
        DB::table('company_service')->delete();
        Company::query()->delete();

        $hubs = $this->malaysiaHubs();
        $names = $this->visitorNames();
        $titles = $this->jobTitles();
        $messages = $this->displayMessages();
        $companyKinds = ['Maps', 'Geo', 'Survey', 'Data', 'Land', 'Spatial', 'Grid', 'Cadastre', 'Digital', 'Terrain'];
        $statuses = ['new', 'contacted', 'follow-up', 'qualified'];
        $serviceSlugs = $services->keys()->values()->all();
        $createdAt = now()->subHours(8);

        for ($i = 0; $i < 100; $i++) {
            // Heavier weight on KL / Selangor / Penang / JB so the map looks busy there.
            $hub = $hubs[$this->hubIndexForVisitor($i, count($hubs))];
            $isPublic = $i < 95;
            [$latitude, $longitude] = $this->offsetAroundHub($hub['lat'], $hub['lng'], $i);

            $kind = $companyKinds[$i % count($companyKinds)];
            $companyName = sprintf('%s %s %03d', $hub['city'], $kind, $i + 1);
            $checkedInAt = $createdAt->copy()->addMinutes($i * 4);

            $company = Company::query()->create([
                'name' => $companyName,
                'city' => $hub['city'],
                'region' => $hub['region'],
                'country' => 'Malaysia',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'public_display_enabled' => $isPublic,
                'public_website' => $isPublic ? 'https://example.com/'.strtolower(str_replace(' ', '-', $hub['city'])).'-'.$i : null,
                'created_at' => $checkedInAt,
                'updated_at' => $checkedInAt,
            ]);

            $picked = collect($serviceSlugs)
                ->shuffle($i + 1)
                ->take(($i % 3) + 1)
                ->map(fn (string $slug) => $services[$slug]->id)
                ->all();
            $company->services()->sync($picked);

            $person = $names[$i % count($names)];
            $fullName = $person.' '.chr(65 + ($i % 26));
            $email = sprintf('visitor%03d@demo.expo.test', $i + 1);
            $status = $statuses[$i % count($statuses)];
            $message = $isPublic ? $messages[$i % count($messages)] : null;

            $visitor = Visitor::query()->create([
                'full_name' => $fullName,
                'job_title' => $titles[$i % count($titles)],
                'email' => $email,
                'phone' => sprintf('+6012%07d', 1000000 + $i),
                'display_message' => $message,
                'avatar_seed' => preg_replace('/\s+/', '', $fullName),
                'company_id' => $company->id,
                'follow_up_consent_at' => $checkedInAt,
                'privacy_notice_version' => '1.0',
                'created_at' => $checkedInAt,
                'updated_at' => $checkedInAt,
            ]);

            Lead::query()->create([
                'visitor_id' => $visitor->id,
                'status' => $status,
                'staff_notes' => $status === 'qualified' ? 'Great booth conversation.' : null,
            ]);
        }

        $this->command?->info('Seeded 100 demo visitors (95 public on the map, 5 private).');
    }

    /**
     * @return list<array{city: string, region: string, lat: float, lng: float}>
     */
    private function malaysiaHubs(): array
    {
        return [
            ['city' => 'Kuala Lumpur', 'region' => 'Wilayah Persekutuan', 'lat' => 3.1579, 'lng' => 101.7123],
            ['city' => 'Petaling Jaya', 'region' => 'Selangor', 'lat' => 3.1073, 'lng' => 101.6067],
            ['city' => 'Shah Alam', 'region' => 'Selangor', 'lat' => 3.0733, 'lng' => 101.5185],
            ['city' => 'Subang Jaya', 'region' => 'Selangor', 'lat' => 3.0565, 'lng' => 101.5851],
            ['city' => 'Cyberjaya', 'region' => 'Selangor', 'lat' => 2.9213, 'lng' => 101.6559],
            ['city' => 'Putrajaya', 'region' => 'Wilayah Persekutuan', 'lat' => 2.9264, 'lng' => 101.6964],
            ['city' => 'George Town', 'region' => 'Penang', 'lat' => 5.4141, 'lng' => 100.3288],
            ['city' => 'Butterworth', 'region' => 'Penang', 'lat' => 5.3991, 'lng' => 100.3638],
            ['city' => 'Johor Bahru', 'region' => 'Johor', 'lat' => 1.4927, 'lng' => 103.7414],
            ['city' => 'Iskandar Puteri', 'region' => 'Johor', 'lat' => 1.4226, 'lng' => 103.6510],
            ['city' => 'Malacca City', 'region' => 'Melaka', 'lat' => 2.1896, 'lng' => 102.2501],
            ['city' => 'Ipoh', 'region' => 'Perak', 'lat' => 4.5975, 'lng' => 101.0901],
            ['city' => 'Seremban', 'region' => 'Negeri Sembilan', 'lat' => 2.7258, 'lng' => 101.9424],
            ['city' => 'Kuantan', 'region' => 'Pahang', 'lat' => 3.8077, 'lng' => 103.3260],
            ['city' => 'Kota Bharu', 'region' => 'Kelantan', 'lat' => 6.1254, 'lng' => 102.2381],
            ['city' => 'Kuching', 'region' => 'Sarawak', 'lat' => 1.5533, 'lng' => 110.3592],
            ['city' => 'Miri', 'region' => 'Sarawak', 'lat' => 4.3995, 'lng' => 113.9914],
            ['city' => 'Kota Kinabalu', 'region' => 'Sabah', 'lat' => 5.9804, 'lng' => 116.0735],
            ['city' => 'Sandakan', 'region' => 'Sabah', 'lat' => 5.8394, 'lng' => 118.1171],
            ['city' => 'Alor Setar', 'region' => 'Kedah', 'lat' => 6.1248, 'lng' => 100.3678],
        ];
    }

    /**
     * Keep messages short so map bubbles stay fully readable.
     *
     * @return list<string>
     */
    private function displayMessages(): array
    {
        return [
            'Hello from Malaysia',
            'Glad we made it',
            'Let’s connect',
            'Here for good ideas',
            'Say hi at the booth',
            'Loving this expo',
            'Geo friends unite',
            'Maps and coffee',
            'Ready to learn',
            'Nice to meet you',
            'Building better maps',
            'See you at NADI',
            'Open to partners',
            'Land team checking in',
            'Data crew is here',
            'Happy to chat',
            'From our office to yours',
            'Excited to join',
            'Looking for GIS tips',
            'Great event so far',
        ];
    }

    /**
     * @return list<string>
     */
    private function visitorNames(): array
    {
        return [
            'Aisha', 'Daniel', 'Nurul', 'Jason', 'Mei Ling', 'Farid', 'Wei Ling', 'Arif',
            'Siti', 'Kevin', 'Rachel', 'Hafiz', 'Priya', 'Azlan', 'Zulaikha', 'Benjamin',
            'Dayang', 'Marcus', 'Elena', 'Rina', 'Hakim', 'Nina', 'Amina', 'Adam',
            'Sofia', 'Imran', 'Jia Yi', 'Hassan', 'Nadia', 'Oliver', 'Fatimah', 'Ryan',
        ];
    }

    /**
     * @return list<string>
     */
    private function jobTitles(): array
    {
        return [
            'GIS Manager', 'Analyst', 'Land Officer', 'CTO', 'Product Lead', 'Systems Lead',
            'Coastal Planner', 'Ops Manager', 'Estate Manager', 'R&D Director', 'Heritage Analyst',
            'Survey Lead', 'Solutions Engineer', 'Field Lead', 'Planner', 'GIS Specialist',
            'Project Manager', 'Geo Lead', 'Founder', 'Forest Analyst', 'Smart City Lead',
            'Director', 'Engineer', 'Consultant',
        ];
    }

    /**
     * Pack most visitors into the first hubs (KL metro, Penang, Johor) so country
     * zoom shows dense crowds instead of a thin even sprinkle.
     */
    private function hubIndexForVisitor(int $index, int $hubCount): int
    {
        $roll = $index % 10;

        if ($roll < 5) {
            return $index % min(6, $hubCount); // KL / Selangor belt
        }

        if ($roll < 7) {
            return 6 + ($index % min(2, max(0, $hubCount - 6))); // Penang
        }

        if ($roll < 8) {
            return 8 + ($index % min(2, max(0, $hubCount - 8))); // Johor
        }

        return $index % $hubCount;
    }

    /**
     * Keep many pins almost on top of each hub so crowds form at country zoom.
     * A smaller share fans out nearby so zooming in still reveals more faces.
     *
     * @return array{0: float, 1: float}
     */
    private function offsetAroundHub(float $lat, float $lng, int $index): array
    {
        if ($index % 3 !== 0) {
            $nudgeLat = (($index % 11) - 5) * 0.00018;
            $nudgeLng = ((($index * 3) % 11) - 5) * 0.00018;

            return [round($lat + $nudgeLat, 6), round($lng + $nudgeLng, 6)];
        }

        $angle = deg2rad(($index * 47) % 360);
        $radius = 0.008 + (($index % 5) * 0.004);

        return [
            round($lat + (sin($angle) * $radius), 6),
            round($lng + (cos($angle) * $radius), 6),
        ];
    }
}
