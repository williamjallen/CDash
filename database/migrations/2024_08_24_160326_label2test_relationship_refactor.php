<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('label2test', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropUnique(['outputid', 'buildid', 'labelid']);
            $table->unsignedInteger('testid')
                ->nullable();
        });

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE label2test
                SET testid = build2test.id
                FROM build2test
                WHERE
                    build2test.buildid = label2test.buildid
                    AND build2test.outputid = label2test.outputid
            ');
        } else {
            DB::update('
                UPDATE label2test, build2test
                SET label2test.testid = build2test.id
                WHERE
                    build2test.buildid = label2test.buildid
                    AND build2test.outputid = label2test.outputid
            ');
        }

        $rows_deleted = DB::delete('DELETE FROM label2test WHERE testid IS NULL');
        if ($rows_deleted > 0) {
            echo "Deleted $rows_deleted invalid rows from label2test.";
        }

        Schema::table('label2test', function (Blueprint $table) {
            $table->dropForeign(['buildid']);
            $table->dropColumn(['buildid', 'outputid']);
            $table->unsignedInteger('testid')
                ->nullable(false)
                ->change();
            $table->foreign('testid')
                ->references('id')
                ->on('build2test')
                ->cascadeOnDelete();
            $table->foreign('labelid')
                ->references('id')
                ->on('label')
                ->cascadeOnDelete();
            $table->unique(['labelid', 'testid']);
            $table->unique(['testid', 'labelid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('label2test', function (Blueprint $table) {
            $table->integer('buildid')
                ->nullable();
            $table->bigInteger('outputid')
                ->nullable();
        });

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE label2test
                SET
                    buildid = build2test.buildid,
                    outputid = build2test.outputid
                FROM build2test
                WHERE build2test.id = label2test.testid
            ');
        } else {
            DB::update('
                UPDATE label2test, build2test
                SET
                    label2test.buildid = build2test.buildid,
                    label2test.outputid = build2test.outputid
                WHERE build2test.id = label2test.testid
            ');
        }

        Schema::table('label2test', function (Blueprint $table) {
            $table->dropForeign(['testid']);
            $table->dropColumn('testid');
            $table->primary(['labelid', 'buildid', 'outputid']);
            $table->dropForeign(['labelid']);
        });
    }
};