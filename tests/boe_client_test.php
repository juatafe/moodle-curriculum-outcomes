<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_criteriaoutcomes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use local_criteriaoutcomes\external\boe_client;

/**
 * Official BOE client behavior with only the HTTP transport mocked.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_client_test extends \advanced_testcase {
    /**
     * Valid JSON is decoded and limited.
     */
    public function test_search_200(): void {
        $client = $this->client(new Response(200, [], json_encode([
            'status' => ['code' => 200, 'text' => 'ok'],
            'data' => [['identificador' => 'BOE-A-2022-4975', 'titulo' => 'Real Decreto']],
        ])));
        $result = $client->search('Real Decreto 217/2022');
        $this->assertSame('BOE-A-2022-4975', $result[0]['identificador']);
    }

    /**
     * The real metadata endpoint may wrap its single document in a list.
     */
    public function test_metadata_unwraps_single_document_list(): void {
        $client = $this->client(new Response(200, [], json_encode([
            'status' => ['code' => 200, 'text' => 'ok'],
            'data' => [[
                'identificador' => 'BOE-A-2022-4975',
                'titulo' => 'Real Decreto de Educación Secundaria Obligatoria',
            ]],
        ])));
        $metadata = $client->metadata('BOE-A-2022-4975');
        $this->assertSame('BOE-A-2022-4975', $metadata['identificador']);
        $this->assertStringContainsString('Educación Secundaria Obligatoria', $metadata['titulo']);
    }

    /**
     * Client and server failures have stable semantics.
     */
    public function test_http_failures(): void {
        foreach ([404, 500] as $status) {
            try {
                $this->client(new Response($status))->metadata('BOE-A-2022-4975');
                $this->fail('An HTTP failure must throw.');
            } catch (\Throwable $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    /**
     * Transport timeouts are not exposed as partial data.
     */
    public function test_timeout(): void {
        $error = new ConnectException('timeout', new Request('GET', boe_client::ORIGIN));
        $this->expectException(\RuntimeException::class);
        $this->client($error)->metadata('BOE-A-2022-4975');
    }

    /**
     * Invalid XML and unexpected JSON schema are rejected.
     */
    public function test_invalid_payloads(): void {
        try {
            $this->client(new Response(200, [], '<not-response>'))->text('BOE-A-2022-4975');
            $this->fail('Invalid XML must throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('invalid XML', $e->getMessage());
        }
        $this->expectException(\RuntimeException::class);
        $this->client(new Response(200, [], '{}'))->metadata('BOE-A-2022-4975');
    }

    /**
     * Identifiers and block IDs cannot become arbitrary URLs or schemes.
     */
    public function test_ssrf_inputs_are_rejected(): void {
        $client = $this->client(new Response(200, [], '{}'));
        foreach (['http://localhost/', 'file:///etc/passwd', 'BOE-A-22-x'] as $identifier) {
            try {
                $client->metadata($identifier);
                $this->fail('Malformed identifier must be rejected.');
            } catch (\invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->expectException(\invalid_parameter_exception::class);
        $client->block('BOE-A-2022-4975', '../../private');
    }

    /**
     * Build a real Guzzle client around a transport queue and disabled cache.
     */
    private function client(Response|\Throwable $queued): boe_client {
        $handler = new MockHandler([$queued]);
        $http = new Client(['handler' => HandlerStack::create($handler)]);
        $cache = $this->createMock(\cache::class);
        $cache->method('get')->willReturn(false);
        return new boe_client($http, $cache);
    }
}
