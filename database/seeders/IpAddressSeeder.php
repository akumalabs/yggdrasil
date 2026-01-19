<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IpAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 50; $i < 60; $i++) {
            \App\Models\IpAddress::create([
                'ip' => "192.168.1.{$i}",
                'gateway' => '192.168.1.1',
                'netmask' => '24',
            ]);
        }
    }
}
