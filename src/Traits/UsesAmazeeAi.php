<?php

namespace Amazeelabs\PolydockAppAmazeeioPrivateGpt\Traits;

use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Client\AmazeeAiClient;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Exceptions\AmazeeAiClientException;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\TeamResponse;
use Amazeelabs\PolydockAppAmazeeioPrivateGpt\Interfaces\LoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait UsesAmazeeAi
{
    protected ?AmazeeAiClient $amazeeAiClient = null;

    protected ?LoggerInterface $amazeeAiLogger = null;

    /**
     * Setup the trait dependencies
     */
    public function setupAmazeeAiTrait(?LoggerInterface $logger = null): void
    {
        $this->amazeeAiLogger = $logger;
    }

    /**
     * Ensure trait is initialized with dependencies
     */
    private function ensureAmazeeAiTraitInitialized(): void
    {
        if ($this->amazeeAiLogger === null && $this instanceof LoggerInterface) {
            $this->setupAmazeeAiTrait($this);
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    protected function getAmazeeAiClient(): AmazeeAiClient
    {
        if (! $this->amazeeAiClient) {
            throw new PolydockAppInstanceStatusFlowException('amazee.ai client not found');
        }

        return $this->amazeeAiClient;
    }

    public function setAmazeeAiClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $this->ensureAmazeeAiTraitInitialized();

        $amazeeAiBackendToken = $appInstance->getKeyValue('amazee-ai-backend-token');
        if (empty($amazeeAiBackendToken)) {
            throw new PolydockAppInstanceStatusFlowException('amazee.ai backend token is required to be set in the app instance');
        }

        $amazeeAiBackendUrl = $appInstance->getKeyValue('amazee-ai-backend-url');
        if (empty($amazeeAiBackendUrl)) {
            $amazeeAiBackendUrl = 'https://backend.main.amazeeai.us2.amazee.io';
        }

        $this->amazeeAiClient = new AmazeeAiClient($amazeeAiBackendToken, $amazeeAiBackendUrl);

        if (! $this->pingAmazeeAi()) {
            throw new PolydockAppInstanceStatusFlowException('amazee.ai API is not healthy');
        }
    }

    public function pingAmazeeAi(): bool
    {
        $this->ensureAmazeeAiTraitInitialized();

        $logContext = $this->amazeeAiLogger?->getLogContext(__FUNCTION__) ?? [];

        try {
            $healthy = $this->getAmazeeAiClient()->ping();

            if ($healthy) {
                $this->amazeeAiLogger?->info('amazee.ai API is healthy', $logContext);

                return true;
            } else {
                $this->amazeeAiLogger?->error('amazee.ai API is not healthy', $logContext);

                return false;
            }
        } catch (AmazeeAiClientException $e) {
            $this->amazeeAiLogger?->error('Error pinging amazee.ai API: '.$e->getMessage(), $logContext);
            throw new PolydockAppInstanceStatusFlowException('Error pinging amazee.ai API: '.$e->getMessage());
        }
    }

    public function createTeamAndSetupAdministrator(PolydockAppInstanceInterface $appInstance): TeamResponse
    {
        $this->ensureAmazeeAiTraitInitialized();

        $logContext = $this->amazeeAiLogger?->getLogContext(__FUNCTION__) ?? [];

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $adminEmail = $appInstance->getKeyValue('amazee-ai-admin-email');

        if (empty($adminEmail)) {
            throw new PolydockAppInstanceStatusFlowException('amazee.ai admin email is required');
        }

        $logContext['project_name'] = $projectName;
        $logContext['admin_email'] = $adminEmail;

        try {
            $this->amazeeAiLogger?->info('Creating team on amazee.ai', $logContext);
            $team = $this->getAmazeeAiClient()->createTeam($projectName, $adminEmail);

            $teamId = $team->id;
            $logContext['team_id'] = $teamId;

            $this->amazeeAiLogger?->info('Team created successfully', $logContext + ['team' => $team]);

            $this->amazeeAiLogger?->info('Setting up team administrator', $logContext);
            $administrator = $this->getAmazeeAiClient()->addTeamAdministrator((string) $teamId, $adminEmail);

            $this->amazeeAiLogger?->info('Team administrator set up successfully', $logContext + ['administrator' => $administrator]);

            return $team;
        } catch (AmazeeAiClientException $e) {
            $this->amazeeAiLogger?->error('Error creating team or setting up administrator: '.$e->getMessage(), $logContext);
            throw new PolydockAppInstanceStatusFlowException('Error creating team or setting up administrator: '.$e->getMessage());
        }
    }

    /**
     * @return array{team_id: string, llm_keys: \Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\LlmKeysResponse, vdb_keys: \Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto\VdbKeysResponse}
     */
    public function generateKeysForTeam(PolydockAppInstanceInterface $appInstance, string $teamId): array
    {
        $this->ensureAmazeeAiTraitInitialized();

        $logContext = $this->amazeeAiLogger?->getLogContext(__FUNCTION__) ?? [];
        $logContext['team_id'] = $teamId;

        try {
            $this->amazeeAiLogger?->info('Generating LLM keys for team', $logContext);
            $llmKeys = $this->getAmazeeAiClient()->generateLlmKeys($teamId);

            $this->amazeeAiLogger?->info('Generating VDB keys for team', $logContext);
            $vdbKeys = $this->getAmazeeAiClient()->generateVdbKeys($teamId);

            $credentials = [
                'team_id' => $teamId,
                'llm_keys' => $llmKeys,
                'vdb_keys' => $vdbKeys,
            ];

            $this->amazeeAiLogger?->info('Keys generated successfully for team', $logContext);

            return $credentials;
        } catch (AmazeeAiClientException $e) {
            $this->amazeeAiLogger?->error('Error generating keys for team: '.$e->getMessage(), $logContext);
            throw new PolydockAppInstanceStatusFlowException('Error generating keys for team: '.$e->getMessage());
        }
    }

    public function getTeamDetails(string $teamId): TeamResponse
    {
        $this->ensureAmazeeAiTraitInitialized();

        $logContext = $this->amazeeAiLogger?->getLogContext(__FUNCTION__) ?? [];
        $logContext['team_id'] = $teamId;

        try {
            $this->amazeeAiLogger?->info('Getting team details', $logContext);
            $team = $this->getAmazeeAiClient()->getTeam($teamId);

            $this->amazeeAiLogger?->info('Team details retrieved successfully', $logContext);

            return $team;
        } catch (AmazeeAiClientException $e) {
            $this->amazeeAiLogger?->error('Error getting team details: '.$e->getMessage(), $logContext);
            throw new PolydockAppInstanceStatusFlowException('Error getting team details: '.$e->getMessage());
        }
    }
}
