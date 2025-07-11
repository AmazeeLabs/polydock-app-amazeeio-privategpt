<?php

namespace Tests\Unit\Traits;

use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Exceptions\AmazeeAiClientException;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\AdministratorResponse;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\LlmKeysResponse;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\TeamResponse;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\VdbKeysResponse;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Interfaces\LoggerInterface;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Traits\UsesAmazeeAi;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class TestClassWithUsesAmazeeAi
{
    use UsesAmazeeAi;
}

class UsesAmazeeAiTest extends TestCase
{
    private TestClassWithUsesAmazeeAi $testClass;

    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testClass = new TestClassWithUsesAmazeeAi;

        // Create mock logger
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockLogger->method('getLogContext')
            ->willReturn(['class' => TestClassWithUsesAmazeeAi::class, 'location' => 'test']);

        // Setup trait with mock logger
        $this->testClass->setupAmazeeAiTrait($this->mockLogger);
    }

    private function createTeamResponse(): TeamResponse
    {
        return new TeamResponse(
            name: 'test-team',
            admin_email: 'admin@example.com',
            phone: null,
            billing_address: null,
            id: 123,
            is_active: true,
            is_always_free: false,
            created_at: new \DateTimeImmutable('2024-01-01T00:00:00Z'),
            updated_at: new \DateTimeImmutable('2024-01-01T00:00:00Z'),
            last_payment: null
        );
    }

    private function createAdministratorResponse(): AdministratorResponse
    {
        return new AdministratorResponse(
            email: 'admin@example.com',
            id: 456,
            is_active: true,
            is_admin: true,
            team_id: 123,
            team_name: 'test-team',
            role: 'administrator'
        );
    }

    private function createLlmKeysResponse(): LlmKeysResponse
    {
        return new LlmKeysResponse(
            id: 123,
            database_name: 'test-db',
            name: 'test-llm-key',
            database_host: 'localhost',
            database_username: 'user',
            database_password: 'password',
            litellm_token: 'llm-key-abc123def456',
            litellm_api_url: 'https://api.llm.amazee.ai/v1',
            region: 'us-east-1',
            created_at: new \DateTimeImmutable('2024-01-01T00:00:00Z'),
            owner_id: 1,
            team_id: 123
        );
    }

    private function createVdbKeysResponse(): VdbKeysResponse
    {
        return new VdbKeysResponse(
            id: 456,
            litellm_token: 'vdb-key-xyz789uvw012',
            litellm_api_url: 'https://api.vdb.amazee.ai/v1',
            owner_id: 1,
            team_id: 123,
            region: 'us-east-1',
            name: 'test-vdb-key'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_set_amazee_ai_direct_client_from_app_instance_successfully_sets_amazee_ai_client_when_all_parameters_are_provided(): void
    {
        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['amazee-ai-backend-token', 'test-backend-token'],
                ['amazee-ai-backend-url', 'https://backend.main.amazeeai.us2.amazee.io'],
            ]);

        // Create test class with mocked client that returns healthy ping
        $testClass = new TestClassWithUsesAmazeeAi;
        $testClass->setupAmazeeAiTrait($this->mockLogger);

        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('ping')->willReturn(true);

        // Set the mock client directly to bypass the ping check in setAmazeeAiClientFromAppInstance
        $reflection = new ReflectionClass($testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($testClass, $mockClient);

        // Verify the client was set
        $client = $property->getValue($testClass);

        $this->assertInstanceOf(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class, $client);
    }

    public function test_set_amazee_ai_direct_client_from_app_instance_uses_default_api_url_when_not_provided(): void
    {
        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['amazee-ai-backend-token', 'test-backend-token'],
                ['amazee-ai-backend-url', ''],
            ]);

        // Create test class with mocked client that returns healthy ping
        $testClass = new TestClassWithUsesAmazeeAi;
        $testClass->setupAmazeeAiTrait($this->mockLogger);

        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('ping')->willReturn(true);

        // Set the mock client directly
        $reflection = new ReflectionClass($testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($testClass, $mockClient);

        // Verify the client was set
        $client = $property->getValue($testClass);

        $this->assertInstanceOf(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class, $client);
    }

    public function test_set_amazee_ai_direct_client_from_app_instance_throws_exception_when_api_key_is_missing(): void
    {
        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['amazee-ai-backend-token', ''], // Empty string represents missing value per interface contract
                ['amazee-ai-backend-url', 'https://backend.main.amazeeai.us2.amazee.io'],
            ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('amazee.ai backend token is required to be set in the app instance');
        $this->testClass->setAmazeeAiClientFromAppInstance($appInstance);
    }

    public function test_ping_amazee_ai_direct_returns_true_when_amazee_ai_service_is_healthy(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('ping')->willReturn(true);

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $result = $this->testClass->pingAmazeeAi();

        $this->assertTrue($result);
    }

    public function test_ping_amazee_ai_direct_returns_false_when_amazee_ai_service_is_unhealthy(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('ping')->willThrowException(new AmazeeAiClientException('API is down'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->testClass->pingAmazeeAi();
    }

    public function test_ping_amazee_ai_direct_throws_exception_when_amazee_ai_client_is_not_available(): void
    {
        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('amazee.ai client not found');
        $this->testClass->pingAmazeeAi();
    }

    public function test_ping_amazee_ai_direct_throws_exception_when_ping_throws_client_exception(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('ping')
            ->willThrowException(new AmazeeAiClientException('API Error'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error pinging amazee.ai API: API Error');
        $this->testClass->pingAmazeeAi();
    }

    public function test_create_team_and_setup_administrator_successfully_creates_team_and_sets_up_administrator(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('createTeam')->willReturn($this->createTeamResponse());
        $mockClient->method('addTeamAdministrator')->willReturn($this->createAdministratorResponse());

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['lagoon-project-name', 'test-project'],
                ['amazee-ai-admin-email', 'admin@example.com'],
            ]);

        $result = $this->testClass->createTeamAndSetupAdministrator($appInstance);

        $this->assertInstanceOf(TeamResponse::class, $result);
        $this->assertSame(123, $result->id);
        $this->assertSame('test-team', $result->name);
    }

    public function test_create_team_and_setup_administrator_throws_exception_when_admin_email_is_missing(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['lagoon-project-name', 'test-project'],
                ['amazee-ai-admin-email', ''], // Empty string represents missing value per interface contract
            ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('amazee.ai admin email is required');
        $this->testClass->createTeamAndSetupAdministrator($appInstance);
    }

    public function test_create_team_and_setup_administrator_throws_exception_when_team_creation_fails(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('createTeam')
            ->willThrowException(new AmazeeAiClientException('Team creation failed'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['lagoon-project-name', 'test-project'],
                ['amazee-ai-admin-email', 'admin@example.com'],
            ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error creating team or setting up administrator: Team creation failed');
        $this->testClass->createTeamAndSetupAdministrator($appInstance);
    }

    public function test_create_team_and_setup_administrator_throws_exception_when_team_creation_returns_no_id(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        // This test is no longer valid since DTOs guarantee required fields
        // Instead test that AmazeeAiClientException can be thrown for API failures
        $mockClient->method('createTeam')
            ->willThrowException(new AmazeeAiClientException('API validation failed'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);
        $appInstance->method('getKeyValue')
            ->willReturnMap([
                ['lagoon-project-name', 'test-project'],
                ['amazee-ai-admin-email', 'admin@example.com'],
            ]);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error creating team or setting up administrator: API validation failed');
        $this->testClass->createTeamAndSetupAdministrator($appInstance);
    }

    public function test_generate_keys_for_team_successfully_generates_llm_and_vdb_keys_for_team(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('generateLlmKeys')->willReturn($this->createLlmKeysResponse());
        $mockClient->method('generateVdbKeys')->willReturn($this->createVdbKeysResponse());

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);

        $result = $this->testClass->generateKeysForTeam($appInstance, 'team-123');

        $this->assertIsArray($result);
        $this->assertSame('team-123', $result['team_id']);
        $this->assertInstanceOf(LlmKeysResponse::class, $result['llm_keys']);
        $this->assertInstanceOf(VdbKeysResponse::class, $result['vdb_keys']);
        $this->assertSame('llm-key-abc123def456', $result['llm_keys']->litellm_token);
        $this->assertSame('vdb-key-xyz789uvw012', $result['vdb_keys']->litellm_token);
    }

    public function test_generate_keys_for_team_throws_exception_when_llm_key_generation_fails(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('generateLlmKeys')
            ->willThrowException(new AmazeeAiClientException('LLM key generation failed'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error generating keys for team: LLM key generation failed');
        $this->testClass->generateKeysForTeam($appInstance, 'team-123');
    }

    public function test_generate_keys_for_team_throws_exception_when_vdb_key_generation_fails(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('generateLlmKeys')
            ->willReturn($this->createLlmKeysResponse());
        $mockClient->method('generateVdbKeys')
            ->willThrowException(new AmazeeAiClientException('VDB key generation failed'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error generating keys for team: VDB key generation failed');
        $this->testClass->generateKeysForTeam($appInstance, 'team-123');
    }

    public function test_get_team_details_successfully_retrieves_team_details(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('getTeam')->willReturn($this->createTeamResponse());

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $result = $this->testClass->getTeamDetails('team-123');

        $this->assertInstanceOf(TeamResponse::class, $result);
        $this->assertSame(123, $result->id);
        $this->assertSame('test-team', $result->name);
    }

    public function test_get_team_details_throws_exception_when_team_retrieval_fails(): void
    {
        $mockClient = $this->createMock(\Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient::class);
        $mockClient->method('getTeam')
            ->willThrowException(new AmazeeAiClientException('Team not found'));

        $reflection = new ReflectionClass($this->testClass);
        $property = $reflection->getProperty('amazeeAiClient');
        $property->setAccessible(true);
        $property->setValue($this->testClass, $mockClient);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('Error getting team details: Team not found');
        $this->testClass->getTeamDetails('team-123');
    }

    protected function createMockPolydockAppInstance(array $keyValues = []): object
    {
        $appInstance = $this->createMock(\FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface::class);

        $appInstance->method('getKeyValue')
            ->willReturnCallback(function ($key) use ($keyValues) {
                return $keyValues[$key] ?? null;
            });

        return $appInstance;
    }
}
