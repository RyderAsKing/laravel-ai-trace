<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\Trace;
use RyderAsKing\LaravelAiTrace\Support\SdkCorrelationStore;
use RyderAsKing\LaravelAiTrace\Support\SdkDeduplicator;

class NonAgentSdkOperationsTest extends TestCase
{
    public function test_image_and_audio_operations_create_completed_spans_with_usage_and_meta(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();
        $this->app->make(SdkCorrelationStore::class)->flush();
        config()->set('ai-trace.record_content_mode', 'redacted');

        $imageInvocation = '00000000-0000-7000-8000-000000000101';

        $imageStart = new \stdClass;
        $imageStart->invocationId = $imageInvocation;
        $imageStart->provider = 'openai';
        $imageStart->model = 'gpt-image-1';
        $imageStart->prompt = new \stdClass;
        $imageStart->prompt->prompt = 'portrait of john@example.com';

        $imageEnd = new \stdClass;
        $imageEnd->invocationId = $imageInvocation;
        $imageEnd->provider = 'openai';
        $imageEnd->model = 'gpt-image-1';
        $imageEnd->response = new \stdClass;
        $imageEnd->response->usage = new \stdClass;
        $imageEnd->response->usage->promptTokens = 14;
        $imageEnd->response->usage->completionTokens = 0;
        $imageEnd->response->meta = new \stdClass;
        $imageEnd->response->meta->provider = 'openai';
        $imageEnd->response->meta->model = 'gpt-image-1';
        $imageEnd->response->images = [new \stdClass];

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\GeneratingImage', [$imageStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\ImageGenerated', [$imageEnd]);

        $imageSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $imageInvocation)->value('id'))->where('name', 'image.generate')->first();
        $this->assertNotNull($imageSpan);
        $this->assertSame('image', $imageSpan->span_type);
        $this->assertNotNull($imageSpan->ended_at);
        $this->assertStringContainsString('[redacted-email]', (string) $imageSpan->input_text);
        $this->assertSame(14, (int) $imageSpan->total_tokens);

        $audioInvocation = '00000000-0000-7000-8000-000000000102';

        $audioStart = new \stdClass;
        $audioStart->invocationId = $audioInvocation;
        $audioStart->provider = 'openai';
        $audioStart->model = 'gpt-4o-mini-tts';
        $audioStart->prompt = new \stdClass;
        $audioStart->prompt->text = 'Call +12025551234';

        $audioEnd = new \stdClass;
        $audioEnd->invocationId = $audioInvocation;
        $audioEnd->provider = 'openai';
        $audioEnd->model = 'gpt-4o-mini-tts';
        $audioEnd->response = new \stdClass;
        $audioEnd->response->audio = 'base64-data';
        $audioEnd->response->meta = new \stdClass;
        $audioEnd->response->meta->provider = 'openai';
        $audioEnd->response->meta->model = 'gpt-4o-mini-tts';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\GeneratingAudio', [$audioStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AudioGenerated', [$audioEnd]);

        $audioSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $audioInvocation)->value('id'))->where('name', 'audio.generate')->first();
        $this->assertNotNull($audioSpan);
        $this->assertSame('audio', $audioSpan->span_type);
        $this->assertNotNull($audioSpan->ended_at);
        $this->assertStringContainsString('[redacted-phone]', (string) $audioSpan->input_text);
    }

    public function test_embeddings_and_reranking_operations_persist_token_and_result_metadata(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();
        $this->app->make(SdkCorrelationStore::class)->flush();

        $embInvocation = '00000000-0000-7000-8000-000000000103';
        $embStart = new \stdClass;
        $embStart->invocationId = $embInvocation;
        $embStart->provider = 'openai';
        $embStart->model = 'text-embedding-3-small';
        $embStart->prompt = new \stdClass;
        $embStart->prompt->inputs = ['a', 'b', 'c'];

        $embEnd = new \stdClass;
        $embEnd->invocationId = $embInvocation;
        $embEnd->response = new \stdClass;
        $embEnd->response->tokens = 77;
        $embEnd->response->embeddings = [[0.1], [0.2], [0.3]];
        $embEnd->response->meta = new \stdClass;
        $embEnd->response->meta->provider = 'openai';
        $embEnd->response->meta->model = 'text-embedding-3-small';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\GeneratingEmbeddings', [$embStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\EmbeddingsGenerated', [$embEnd]);

        $embTrace = Trace::query()->where('trace_id', $embInvocation)->first();
        $this->assertNotNull($embTrace);
        $this->assertSame(77, (int) $embTrace->total_tokens);

        $embSpan = Span::query()->where('trace_id', $embTrace->id)->where('name', 'embedding.generate')->first();
        $this->assertNotNull($embSpan);
        $this->assertSame(77, (int) $embSpan->total_tokens);

        $rerankInvocation = '00000000-0000-7000-8000-000000000104';
        $rerankStart = new \stdClass;
        $rerankStart->invocationId = $rerankInvocation;
        $rerankStart->provider = 'cohere';
        $rerankStart->model = 'rerank-v3.5';
        $rerankStart->prompt = new \stdClass;
        $rerankStart->prompt->query = 'best match';
        $rerankStart->prompt->documents = ['doc1', 'doc2'];

        $rerankEnd = new \stdClass;
        $rerankEnd->invocationId = $rerankInvocation;
        $rerankEnd->response = new \stdClass;
        $rerankEnd->response->results = [new \stdClass, new \stdClass];
        $rerankEnd->response->meta = new \stdClass;
        $rerankEnd->response->meta->provider = 'cohere';
        $rerankEnd->response->meta->model = 'rerank-v3.5';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\Reranking', [$rerankStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\Reranked', [$rerankEnd]);

        $rerankSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $rerankInvocation)->value('id'))->where('name', 'reranking.score')->first();
        $this->assertNotNull($rerankSpan);
        $this->assertSame('reranking', $rerankSpan->span_type);
        $this->assertNotNull($rerankSpan->ended_at);
    }

    public function test_file_and_store_operations_support_pair_and_post_only_lifecycle(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();
        $this->app->make(SdkCorrelationStore::class)->flush();

        $fileStoreInvocation = '00000000-0000-7000-8000-000000000105';
        $storeStart = new \stdClass;
        $storeStart->invocationId = $fileStoreInvocation;
        $storeStart->provider = 'openai';
        $storeStart->file = new \stdClass;
        $storeStart->file->name = 'secret.pdf';

        $storeEnd = new \stdClass;
        $storeEnd->invocationId = $fileStoreInvocation;
        $storeEnd->provider = 'openai';
        $storeEnd->response = new \stdClass;
        $storeEnd->response->id = 'file_123';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\StoringFile', [$storeStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\FileStored', [$storeEnd]);

        $fileStoreSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $fileStoreInvocation)->value('id'))->where('name', 'file.store')->first();
        $this->assertNotNull($fileStoreSpan);
        $this->assertNotNull($fileStoreSpan->ended_at);

        $fileDeleteInvocation = '00000000-0000-7000-8000-000000000106';
        $fileDeleted = new \stdClass;
        $fileDeleted->invocationId = $fileDeleteInvocation;
        $fileDeleted->provider = 'openai';
        $fileDeleted->fileId = 'file_to_delete';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\FileDeleted', [$fileDeleted]);

        $fileDeleteSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $fileDeleteInvocation)->value('id'))->where('name', 'file.delete')->first();
        $this->assertNotNull($fileDeleteSpan);
        $this->assertNotNull($fileDeleteSpan->ended_at);

        $storeDeleteInvocation = '00000000-0000-7000-8000-000000000107';
        $storeDeleted = new \stdClass;
        $storeDeleted->invocationId = $storeDeleteInvocation;
        $storeDeleted->provider = 'openai';
        $storeDeleted->storeId = 'store_123';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\StoreDeleted', [$storeDeleted]);

        $storeDeleteSpan = Span::query()->where('trace_id', Trace::query()->where('trace_id', $storeDeleteInvocation)->value('id'))->where('name', 'store.delete')->first();
        $this->assertNotNull($storeDeleteSpan);
        $this->assertNotNull($storeDeleteSpan->ended_at);
    }
}
