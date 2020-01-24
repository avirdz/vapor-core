<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Laravel\Vapor\Runtime\Fpm\Fpm;
use Laravel\Vapor\Runtime\Fpm\FpmLambdaResponse;
use Laravel\Vapor\Runtime\Fpm\FpmRequest;
use Laravel\Vapor\Contracts\LambdaEventHandler;

class FpmHandler implements LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @return  \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        if (isset($event['requestContext']['connectionId'])) {
            try {
                $body = json_decode($event['body']);
                $event['httpMethod'] = 'POST';
                $event['path'] = $event['body']['event'] ?? '/' . $event['requestContext']['routeKey'];
                $event['body'] = json_encode(array_merge($event['requestContext'], ['body' => $body]));

                if (isset($event['multiValueHeaders'])) {
                    $event['multiValueHeaders']['Content-Type'][] = 'application/json';
                } else {
                    $event['headers']['Content-Type'] = 'application/json';
                }
            } catch (\Exception $e) {
        
            }
        }

        return $this->response(
            Fpm::resolve()->handle($this->request($event))
        );
    }

    /**
     * Create a new fpm request from the incoming event.
     *
     * @param  array  $event
     * @return \Laravel\Vapor\Runtime\Fpm\FpmRequest
     */
    public function request($event)
    {
        return FpmRequest::fromLambdaEvent(
            $event, Fpm::resolve()->handler(), $this->serverVariables()
        );
    }

    /**
     * Covert FPM response to Lambda-ready response.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmResponse  $fpmResponse
     * @return \Laravel\Vapor\Runtime\Fpm\FpmLambdaResponse
     */
    public function response($fpmResponse)
    {
        return new FpmLambdaResponse(
            $fpmResponse->status,
            $fpmResponse->headers,
            $fpmResponse->body
        );
    }

    /**
     * Get the server variables.
     *
     * @return array
     */
    public function serverVariables()
    {
        return array_merge(Fpm::resolve()->serverVariables(), array_filter([
            'AWS_REQUEST_ID' => $_ENV['AWS_REQUEST_ID'] ?? null,
        ]));
    }
}
