<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('personas', fn (Blueprint $collection) => $collection->unique(['tenant_id' => 1, 'influencer_id' => 1], 'personas_tenant_influencer_unique'));
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('personas', fn (Blueprint $collection) => $collection->dropIndexIfExists('personas_tenant_influencer_unique'));
    }
};
