<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->comment('Widget grafici e tabelle generati dalle righe dello storico chat');
            
            $table->id()->comment('ID univoco del widget');
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete()->comment('Dashboard genitore');
            $table->foreignId('chat_history_id')->nullable()->constrained('chat_histories')->nullOnDelete()->comment('Riferimento alla riga di cronologia sorgente');
            $table->string('title')->comment('Titolo del widget');
            $table->string('type')->comment('Tipo di componente (es. table, bar_chart, line_chart, kpi_card)');
            $table->text('query')->nullable()->comment('Query SQL o definizione della vista generata');
            $table->json('settings')->nullable()->comment('Mappatura campi, assi X/Y, colori e filtri del grafico');
            $table->json('grid_position')->nullable()->comment('Posizionamento e dimensioni della griglia (x, y, width, height)');
            $table->integer('order')->default(0)->comment('Ordine di resa all\'interno della dashboard');
            $table->boolean('is_active')->default(true)->comment('Stato di visibilità del widget');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index('histories_user_id_foreign');
            $table->string('share_token')->nullable()->unique('histories_share_token_unique');
            $table->timestamp('share_expires_at')->nullable();
            $table->integer('dashboardorder')->default(0);
            $table->unsignedBigInteger('masterquery')->nullable();
            $table->integer('slavedashboard')->default(0);
            $table->timestamp('submission_date')->nullable();
            $table->text('message')->nullable();
            $table->text('sqlstatement')->nullable();
            $table->string('charttype')->default('Pie Chart');
            $table->unsignedInteger('nviewed')->default(0);
            $table->timestamps();
            $table->string('database_name')->nullable();
            $table->unsignedBigInteger('categorymenu_id')->nullable()->index('categorymenu_id');

            // Se occorre abilitare i vincoli di foreign key reali nel database:
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('categorymenu_id')->references('id')->on('category_menus')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};