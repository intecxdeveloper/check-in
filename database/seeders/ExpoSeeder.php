<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ExpoSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::query()->updateOrCreate(
            ['email' => 'staff@expo.test'],
            [
                'name' => 'Expo Staff',
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'amina@expo.test'],
            [
                'name' => 'Amina Lee',
                'password' => Hash::make('password'),
            ]
        );

        $services = collect([
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

        $samples = [
            [
                'company' => [
                    'name' => 'KL Spatial Hub',
                    'city' => 'Kuala Lumpur',
                    'region' => 'Wilayah Persekutuan',
                    'country' => 'Malaysia',
                    'latitude' => 3.1579,
                    'longitude' => 101.7123,
                    'public_display_enabled' => true,
                    'public_website' => 'https://example.com/kl-spatial',
                ],
                'services' => ['gis', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Aisha Rahman', 'job_title' => 'GIS Manager', 'email' => 'aisha@klspatial.my', 'status' => 'qualified'],
                    ['full_name' => 'Daniel Tan', 'job_title' => 'Analyst', 'email' => 'daniel@klspatial.my', 'status' => 'follow-up'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Petaling Cadastre Lab',
                    'city' => 'Petaling Jaya',
                    'region' => 'Selangor',
                    'country' => 'Malaysia',
                    'latitude' => 3.1073,
                    'longitude' => 101.6067,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'gis'],
                'visitors' => [
                    ['full_name' => 'Nurul Izzah', 'job_title' => 'Land Officer', 'email' => 'nurul@pjcadastre.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Cyberjaya Dataworks',
                    'city' => 'Cyberjaya',
                    'region' => 'Selangor',
                    'country' => 'Malaysia',
                    'latitude' => 2.9213,
                    'longitude' => 101.6559,
                    'public_display_enabled' => true,
                ],
                'services' => ['software', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Jason Lim', 'job_title' => 'CTO', 'email' => 'jason@cyberdata.my', 'status' => 'contacted'],
                    ['full_name' => 'Mei Ling Ong', 'job_title' => 'Product Lead', 'email' => 'meiling@cyberdata.my', 'status' => 'follow-up'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Putrajaya Land Systems',
                    'city' => 'Putrajaya',
                    'region' => 'Wilayah Persekutuan',
                    'country' => 'Malaysia',
                    'latitude' => 2.9264,
                    'longitude' => 101.6964,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'integration'],
                'visitors' => [
                    ['full_name' => 'Farid Hassan', 'job_title' => 'Systems Lead', 'email' => 'farid@pjland.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Penang Coastal GIS',
                    'city' => 'George Town',
                    'region' => 'Penang',
                    'country' => 'Malaysia',
                    'latitude' => 5.4141,
                    'longitude' => 100.3288,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'other'],
                'visitors' => [
                    ['full_name' => 'Wei Ling Ong', 'job_title' => 'Coastal Planner', 'email' => 'weiling@penanggis.my', 'status' => 'qualified'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Butterworth Port Maps',
                    'city' => 'Butterworth',
                    'region' => 'Penang',
                    'country' => 'Malaysia',
                    'latitude' => 5.3991,
                    'longitude' => 100.3638,
                    'public_display_enabled' => true,
                ],
                'services' => ['integration', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Arif Ismail', 'job_title' => 'Ops Manager', 'email' => 'arif@bwport.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Johor Digital Estate',
                    'city' => 'Johor Bahru',
                    'region' => 'Johor',
                    'country' => 'Malaysia',
                    'latitude' => 1.4927,
                    'longitude' => 103.7414,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'software'],
                'visitors' => [
                    ['full_name' => 'Siti Aminah', 'job_title' => 'Estate Manager', 'email' => 'siti@johordigital.my', 'status' => 'contacted'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Iskandar GeoForge',
                    'city' => 'Iskandar Puteri',
                    'region' => 'Johor',
                    'country' => 'Malaysia',
                    'latitude' => 1.4226,
                    'longitude' => 103.6510,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'software', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Kevin Yap', 'job_title' => 'R&D Director', 'email' => 'kevin@iskandargeo.my', 'status' => 'follow-up'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Melaka Heritage Maps',
                    'city' => 'Malacca City',
                    'region' => 'Melaka',
                    'country' => 'Malaysia',
                    'latitude' => 2.1896,
                    'longitude' => 102.2501,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'other'],
                'visitors' => [
                    ['full_name' => 'Rachel Chong', 'job_title' => 'Heritage Analyst', 'email' => 'rachel@melakamaps.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Ipoh Survey Collective',
                    'city' => 'Ipoh',
                    'region' => 'Perak',
                    'country' => 'Malaysia',
                    'latitude' => 4.5975,
                    'longitude' => 101.0901,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'gis'],
                'visitors' => [
                    ['full_name' => 'Hafiz Rahman', 'job_title' => 'Survey Lead', 'email' => 'hafiz@ipohsurvey.my', 'status' => 'qualified'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Seremban Terrain Stack',
                    'city' => 'Seremban',
                    'region' => 'Negeri Sembilan',
                    'country' => 'Malaysia',
                    'latitude' => 2.7258,
                    'longitude' => 101.9424,
                    'public_display_enabled' => true,
                ],
                'services' => ['software', 'integration'],
                'visitors' => [
                    ['full_name' => 'Priya Nadarajah', 'job_title' => 'Solutions Engineer', 'email' => 'priya@nsterrain.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Kuantan Coastal Ops',
                    'city' => 'Kuantan',
                    'region' => 'Pahang',
                    'country' => 'Malaysia',
                    'latitude' => 3.8077,
                    'longitude' => 103.3260,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Azlan Yusof', 'job_title' => 'Field Lead', 'email' => 'azlan@kuantanops.my', 'status' => 'contacted'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Kota Bharu LandLink',
                    'city' => 'Kota Bharu',
                    'region' => 'Kelantan',
                    'country' => 'Malaysia',
                    'latitude' => 6.1254,
                    'longitude' => 102.2381,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'other'],
                'visitors' => [
                    ['full_name' => 'Zulaikha Mat', 'job_title' => 'Planner', 'email' => 'zulaikha@kblandlink.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Kuching River Maps',
                    'city' => 'Kuching',
                    'region' => 'Sarawak',
                    'country' => 'Malaysia',
                    'latitude' => 1.5533,
                    'longitude' => 110.3592,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'land'],
                'visitors' => [
                    ['full_name' => 'Benjamin Lau', 'job_title' => 'GIS Specialist', 'email' => 'ben@kuchingmaps.my', 'status' => 'follow-up'],
                    ['full_name' => 'Dayang Aida', 'job_title' => 'Project Manager', 'email' => 'aida@kuchingmaps.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Miri Energy Geo',
                    'city' => 'Miri',
                    'region' => 'Sarawak',
                    'country' => 'Malaysia',
                    'latitude' => 4.3995,
                    'longitude' => 113.9914,
                    'public_display_enabled' => true,
                ],
                'services' => ['analytics', 'integration'],
                'visitors' => [
                    ['full_name' => 'Marcus Chin', 'job_title' => 'Geo Lead', 'email' => 'marcus@mirigeo.my', 'status' => 'qualified'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Kota Kinabalu SkyMaps',
                    'city' => 'Kota Kinabalu',
                    'region' => 'Sabah',
                    'country' => 'Malaysia',
                    'latitude' => 5.9804,
                    'longitude' => 116.0735,
                    'public_display_enabled' => true,
                ],
                'services' => ['gis', 'software'],
                'visitors' => [
                    ['full_name' => 'Elena Wong', 'job_title' => 'Founder', 'email' => 'elena@kkskymaps.my', 'status' => 'contacted'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Sandakan Forest Plot',
                    'city' => 'Sandakan',
                    'region' => 'Sabah',
                    'country' => 'Malaysia',
                    'latitude' => 5.8394,
                    'longitude' => 118.1171,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'gis'],
                'visitors' => [
                    ['full_name' => 'Rina Joseph', 'job_title' => 'Forest Analyst', 'email' => 'rina@sandakanplot.my', 'status' => 'new'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Alor Setar Padi Maps',
                    'city' => 'Alor Setar',
                    'region' => 'Kedah',
                    'country' => 'Malaysia',
                    'latitude' => 6.1248,
                    'longitude' => 100.3678,
                    'public_display_enabled' => true,
                ],
                'services' => ['land', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Hakim Abdullah', 'job_title' => 'Agri GIS Lead', 'email' => 'hakim@aspadimaps.my', 'status' => 'follow-up'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Shah Alam Civic Grid',
                    'city' => 'Shah Alam',
                    'region' => 'Selangor',
                    'country' => 'Malaysia',
                    'latitude' => 3.0733,
                    'longitude' => 101.5185,
                    'public_display_enabled' => true,
                ],
                'services' => ['software', 'integration', 'analytics'],
                'visitors' => [
                    ['full_name' => 'Nina Teoh', 'job_title' => 'Smart City Lead', 'email' => 'nina@sacivic.my', 'status' => 'qualified'],
                ],
            ],
            [
                'company' => [
                    'name' => 'Private Visitor Group',
                    'city' => 'Subang Jaya',
                    'region' => 'Selangor',
                    'country' => 'Malaysia',
                    'latitude' => 3.0565,
                    'longitude' => 101.5851,
                    'public_display_enabled' => false,
                ],
                'services' => ['other'],
                'visitors' => [
                    ['full_name' => 'Private Guest', 'job_title' => 'Director', 'email' => 'private@visitor.my', 'status' => 'new'],
                ],
            ],
        ];

        $createdAt = now()->subHours(count($samples));

        foreach ($samples as $index => $sample) {
            $company = Company::query()->updateOrCreate(
                ['name' => $sample['company']['name']],
                [
                    ...$sample['company'],
                    'created_at' => $createdAt->copy()->addMinutes($index * 7),
                    'updated_at' => $createdAt->copy()->addMinutes($index * 7),
                ]
            );

            $ids = collect($sample['services'])
                ->map(fn (string $slug) => $services[$slug]->id)
                ->all();

            $company->services()->sync($ids);

            $funMessages = [
                'Hello from Malaysia!',
                'Glad we made it ✨',
                'Let’s connect!',
                'Maps + coffee please',
                'Here for good ideas',
                'Say hi at the booth',
                'Loving this expo',
                'Geo friends unite',
            ];

            foreach ($sample['visitors'] as $visitorIndex => $visitorData) {
                $status = $visitorData['status'] ?? 'new';
                unset($visitorData['status']);

                $checkedInAt = $createdAt->copy()->addMinutes(($index * 7) + ($visitorIndex * 2));
                $message = $visitorData['display_message']
                    ?? ($company->public_display_enabled
                        ? $funMessages[($index + $visitorIndex) % count($funMessages)]
                        : null);
                unset($visitorData['display_message']);

                $visitor = Visitor::query()->updateOrCreate(
                    ['email' => $visitorData['email']],
                    [
                        ...$visitorData,
                        'display_message' => $message,
                        'company_id' => $company->id,
                        'follow_up_consent_at' => $checkedInAt,
                        'privacy_notice_version' => '1.0',
                        'created_at' => $checkedInAt,
                        'updated_at' => $checkedInAt,
                    ]
                );

                Lead::query()->updateOrCreate(
                    ['visitor_id' => $visitor->id],
                    [
                        'status' => $status,
                        'assigned_to' => in_array($status, ['new'], true) ? null : $staff->id,
                        'staff_notes' => $status === 'qualified' ? 'Great booth conversation.' : null,
                    ]
                );
            }
        }
    }
}
