<?php

namespace Tests\Unit;

use App\Http\Responses\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Created.', 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(
            '{"status":"success","data":{"id":1},"errors":{},"hasError":false,"message":"Created."}',
            $response->getContent(),
        );
    }

    public function test_empty_data_is_an_object(): void
    {
        $this->assertSame(
            '{"status":"success","data":{},"errors":{},"hasError":false,"message":""}',
            ApiResponse::success()->getContent(),
        );
    }

    public function test_scalar_data_is_kept(): void
    {
        $this->assertSame(0, json_decode((string) ApiResponse::success(0)->getContent(), true)['data']);
    }

    public function test_fail_with_a_message_list(): void
    {
        $response = ApiResponse::fail('Forbidden', 403);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            '{"status":"fail","data":{},"errors":["Forbidden"],"hasError":true,"message":"Forbidden"}',
            $response->getContent(),
        );
    }

    public function test_fail_with_field_errors_uses_the_first_message(): void
    {
        $body = json_decode((string) ApiResponse::fail(['email' => ['Taken.', 'Invalid.'], 'name' => ['Required.']], 422)->getContent(), true);

        $this->assertSame(['email' => ['Taken.', 'Invalid.'], 'name' => ['Required.']], $body['errors']);
        $this->assertSame('Taken.', $body['message']);
    }
}
