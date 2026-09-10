<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('memories', fn (Blueprint $collection) => $collection->index(['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'importance_score' => -1], 'memories_tenant_influencer_user_importance_index'));
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('memories', fn (Blueprint $collection) => $collection->dropIndexIfExists('memories_tenant_influencer_user_importance_index'));
    }
};
