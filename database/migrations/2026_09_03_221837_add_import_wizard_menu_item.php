<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('menu_items')->where('route', 'voyager.import-wizard.index')->exists();
        if ($exists) {
            return;
        }

        $menuId = DB::table('menus')->where('name', 'admin')->value('id') ?? 1;
        $maxOrder = DB::table('menu_items')->where('menu_id', $menuId)->max('order') ?? 0;

        DB::table('menu_items')->insert([
            'menu_id' => $menuId,
            'title' => 'Importador de Datos',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-upload',
            'color' => null,
            'parent_id' => null,
            'order' => $maxOrder + 1,
            'route' => 'voyager.import-wizard.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')->where('route', 'voyager.import-wizard.index')->delete();
    }
};
