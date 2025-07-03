<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;

class OpenRouter
{
    public static function make(): Client
    {
        $client = OpenAI::factory()
            ->withApiKey(env("OPENROUTER_KEY", ''))
            ->withBaseUri('https://openrouter.ai/api/v1')
            ->withHttpHeader('HTTP-Referer', 'https://forgelink.co')
            ->withHttpHeader('X-Title', 'AnswerEngine')
            ->make();

        return $client;
    }
}
