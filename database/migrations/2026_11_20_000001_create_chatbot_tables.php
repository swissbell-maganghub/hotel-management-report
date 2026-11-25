<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Knowledge base managed by IT (spec 17): glossary terms, app guides, FAQ answers.
        Schema::create('chatbot_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('type', 20)->default('faq'); // glossary | guide | faq
            $table->string('title', 150);
            // One rule per line: groups joined by "+", alternatives by "|"; "=term" matches the whole message.
            $table->text('triggers');
            $table->text('body_id');
            $table->text('body_en')->nullable();
            $table->json('roles')->nullable(); // null = every role
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        // One row per chatbot answer: the improvement loop (ratings, unmatched questions, latency). No answer text stored.
        Schema::create('chatbot_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_excerpt', 200);
            $table->string('intent', 40)->nullable();
            $table->string('source', 60);
            $table->string('filters', 200)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedTinyInteger('llm_calls')->default(0);
            $table->boolean('fallback')->default(false);
            $table->tinyInteger('rating')->nullable(); // 1 = 👍, -1 = 👎
            $table->string('comment', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_turns');
        Schema::dropIfExists('chatbot_knowledge');
    }
};
