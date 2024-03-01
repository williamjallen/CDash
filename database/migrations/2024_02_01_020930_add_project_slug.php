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
        // We assume that if the column already exists, it's due to a failed previous migration.
        // In theory, this should never occur, but you never know what's going to happen out there in the real world...
        if (Schema::hasColumn('project', 'slug')) {
            Schema::dropColumns('project', 'slug');
        }

        Schema::table('project', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable();
        });

        // Wrap everything in a single transaction so if something goes wrong we can easily revert it
        DB::transaction(function () {
            // Doing the processing in PHP like this is a terrible hack, but is unfortunately the only
            // reasonable way of doing this given that we have to support two different databases and
            // have to allow only a limited number of characters.

            $projects = DB::select('SELECT id, name FROM project');
            foreach ($projects as $project) {
                $slug = preg_replace("/[^a-zA-Z0-9\-_]/", '_', $project->name);
                // The sequence _-_ is used as a separator in submission file names, and is thus disallowed
                $slug = str_replace('_-_', '_', $slug);
                DB::table('project')
                    ->where('id', $project->id)
                    ->update(['slug' => $slug]);
            }
        });

        // Make the slug column non-null
        Schema::table('project', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable(false)->change();
        });

        // Laravel doesn't currently support CHECK constraints
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE project ADD CONSTRAINT project_slug_check CHECK(slug RLIKE '^[a-zA-Z0-9\-_]*$')");
        } else {
            DB::statement("ALTER TABLE project ADD CONSTRAINT project_slug_check CHECK(slug ~ '^[a-zA-Z0-9\-_]*$')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropColumns('project', 'slug');
    }
};
