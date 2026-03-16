<?php

namespace Database\Seeders;

use App\Models\Slide;
use Illuminate\Database\Seeder;

class SlideSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding slides...');

        Slide::create([
            'title' => 'Portfolio Microservices',
            'type' => 'html',
            'html_content' => '<div class="flex items-center justify-center h-full bg-gradient-to-br from-sky-600 to-indigo-800 text-white p-12">
                <div class="max-w-2xl text-center">
                    <h2 class="text-4xl font-bold mb-4">Portfolio Microservices</h2>
                    <p class="text-lg text-sky-100 mb-6">Nowoczesna architektura mikroserwisowa zbudowana z Laravel, Filament, RabbitMQ, Docker i Kubernetes.</p>
                    <div class="flex flex-wrap justify-center gap-3 text-sm">
                        <span class="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">Laravel 12</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">Filament 3</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">RabbitMQ</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">Docker</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">Kubernetes</span>
                    </div>
                </div>
            </div>',
            'position' => 0,
            'is_active' => true,
        ]);

        Slide::create([
            'title' => 'Architektura',
            'type' => 'html',
            'html_content' => '<div class="flex items-center justify-center h-full bg-gradient-to-br from-emerald-600 to-teal-800 text-white p-12">
                <div class="max-w-3xl">
                    <h2 class="text-3xl font-bold mb-6 text-center">Architektura systemu</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center text-sm">
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">🌐</div>
                            <div class="font-semibold">Frontend</div>
                            <div class="text-emerald-200 text-xs">Blade + Tailwind</div>
                        </div>
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">🔐</div>
                            <div class="font-semibold">SSO</div>
                            <div class="text-emerald-200 text-xs">OAuth 2.0 + Passport</div>
                        </div>
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">📝</div>
                            <div class="font-semibold">Blog API</div>
                            <div class="text-emerald-200 text-xs">REST + JWT</div>
                        </div>
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">👥</div>
                            <div class="font-semibold">Users API</div>
                            <div class="text-emerald-200 text-xs">RBAC</div>
                        </div>
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">📊</div>
                            <div class="font-semibold">Analytics</div>
                            <div class="text-emerald-200 text-xs">RabbitMQ consumer</div>
                        </div>
                        <div class="bg-white/15 rounded-lg p-4 backdrop-blur-sm">
                            <div class="text-2xl mb-1">⚙️</div>
                            <div class="font-semibold">Admin</div>
                            <div class="text-emerald-200 text-xs">Filament 3</div>
                        </div>
                    </div>
                </div>
            </div>',
            'position' => 1,
            'is_active' => true,
        ]);

        Slide::create([
            'title' => 'O mnie',
            'type' => 'html',
            'html_content' => '<div class="flex items-center justify-center h-full bg-gradient-to-br from-violet-600 to-purple-800 text-white p-12">
                <div class="max-w-2xl text-center">
                    <h2 class="text-3xl font-bold mb-4">Szymon Borowski</h2>
                    <p class="text-lg text-violet-200 mb-6">Software Developer z pasją do czystej architektury, mikroserwisów i automatyzacji infrastruktury.</p>
                    <div class="flex justify-center gap-4">
                        <a href="https://github.com/szymonborowski" class="inline-flex items-center px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition backdrop-blur-sm">GitHub</a>
                        <a href="https://linkedin.com/in/szymonborowski" class="inline-flex items-center px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition backdrop-blur-sm">LinkedIn</a>
                    </div>
                </div>
            </div>',
            'position' => 2,
            'is_active' => true,
        ]);

        $this->command->info('Slides seeding completed! Created ' . Slide::count() . ' slides.');
    }
}
