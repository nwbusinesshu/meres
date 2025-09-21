<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add new JSON columns to competency table
        Schema::table('competency', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
            $table->string('original_language', 2)->default('hu')->after('name_json');
            $table->json('available_languages')->nullable()->after('original_language');
        });

        // Add new JSON columns to competency_question table
        Schema::table('competency_question', function (Blueprint $table) {
            $table->json('question_json')->nullable()->after('question');
            $table->json('question_self_json')->nullable()->after('question_self');
            $table->json('min_label_json')->nullable()->after('min_label');
            $table->json('max_label_json')->nullable()->after('max_label');
            $table->string('original_language', 2)->default('hu')->after('max_value');
            $table->json('available_languages')->nullable()->after('original_language');
        });

        // Migrate existing data to JSON format
        $this->migrateExistingData();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competency_question', function (Blueprint $table) {
            $table->dropColumn([
                'question_json', 
                'question_self_json', 
                'min_label_json', 
                'max_label_json',
                'original_language',
                'available_languages'
            ]);
        });

        Schema::table('competency', function (Blueprint $table) {
            $table->dropColumn(['name_json', 'original_language', 'available_languages']);
        });
    }

    /**
     * Migrate existing text data to JSON format
     */
    private function migrateExistingData()
    {
        $currentLocale = config('app.locale', 'hu');

        echo "Migrating existing competency data...\n";

        // Migrate competency names
        $competencies = DB::table('competency')
            ->whereNull('removed_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->get();

        echo "Found " . count($competencies) . " competencies to migrate\n";

        foreach ($competencies as $comp) {
            $nameJson = json_encode([$currentLocale => $comp->name], JSON_UNESCAPED_UNICODE);
            $availableLanguages = json_encode([$currentLocale], JSON_UNESCAPED_UNICODE);

            DB::table('competency')
                ->where('id', $comp->id)
                ->update([
                    'name_json' => $nameJson,
                    'original_language' => $currentLocale,
                    'available_languages' => $availableLanguages,
                ]);
            
            echo "Migrated competency: {$comp->name}\n";
        }

        // Migrate competency questions
        $questions = DB::table('competency_question')
            ->whereNull('removed_at')
            ->get();

        echo "Found " . count($questions) . " questions to migrate\n";

        foreach ($questions as $question) {
            $questionJson = json_encode([$currentLocale => $question->question], JSON_UNESCAPED_UNICODE);
            $questionSelfJson = json_encode([$currentLocale => $question->question_self], JSON_UNESCAPED_UNICODE);
            $minLabelJson = json_encode([$currentLocale => $question->min_label], JSON_UNESCAPED_UNICODE);
            $maxLabelJson = json_encode([$currentLocale => $question->max_label], JSON_UNESCAPED_UNICODE);
            $availableLanguages = json_encode([$currentLocale], JSON_UNESCAPED_UNICODE);

            DB::table('competency_question')
                ->where('id', $question->id)
                ->update([
                    'question_json' => $questionJson,
                    'question_self_json' => $questionSelfJson,
                    'min_label_json' => $minLabelJson,
                    'max_label_json' => $maxLabelJson,
                    'original_language' => $currentLocale,
                    'available_languages' => $availableLanguages,
                ]);
        }

        echo "Migration completed successfully!\n";
    }
};