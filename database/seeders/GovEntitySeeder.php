<?php

namespace Database\Seeders;

use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class GovEntitySeeder extends Seeder
{
    /**
     * Seed the single V1 ministry, its service, and demo accounts (password: "password").
     */
    public function run(): void
    {
        $interior = GovEntity::factory()->interior()->create();

        Service::factory()->goodConduct()->for($interior)->create();

        User::factory()->employee($interior)->create([
            'name' => 'Mariam Al-Kuwari',
            'name_ar' => 'مريم الكواري',
            'email' => 'reviewer@moi.sahl.test',
        ]);

        User::factory()->create([
            'national_id' => '28763404821',
            'name' => 'Ahmed Al-Mansouri',
            'name_ar' => 'أحمد المنصوري',
            'email' => 'ahmed@example.test',
        ]);

        User::factory()->create([
            'national_id' => '29163401177',
            'name' => 'Noora Al-Rashed',
            'name_ar' => 'نورة الراشد',
            'email' => 'noora@example.test',
        ]);
    }
}
