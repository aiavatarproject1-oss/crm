<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RAG retrieval
    |--------------------------------------------------------------------------
    |
    | Production retrieval quality knobs. Vector backends stay behind
    | RetrievalStrategyInterface / VectorStoreInterface.
    |
    */

    'min_score' => (float) env('RAG_MIN_SCORE', 0.25),

    'max_context_tokens' => (int) env('RAG_MAX_CONTEXT_TOKENS', 1500),

    /*
    | Fetch this many times the requested limit from the vector store so
    | score threshold and metadata filters can still fill the budget.
    */
    'candidate_multiplier' => (int) env('RAG_CANDIDATE_MULTIPLIER', 4),

];
