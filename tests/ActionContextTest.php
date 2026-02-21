<?php

namespace BrianHenryIE\PHPUnitFailedTestsAction;

use PHPUnit\Framework\TestCase;

class ActionContextTest extends TestCase
{
    /**
     * @test
     */
    public function it_uses_head_ref_on_pull_request_events(): void
    {
        putenv('GITHUB_HEAD_REF=feature-branch');
        putenv('GITHUB_REF_NAME=250/merge');

        try {
            $this->assertSame('feature-branch', (new ActionContext())->getBranch());
        } finally {
            putenv('GITHUB_HEAD_REF');
            putenv('GITHUB_REF_NAME');
        }
    }

    /**
     * @test
     */
    public function it_falls_back_to_ref_name_on_push_events(): void
    {
        putenv('GITHUB_HEAD_REF=');
        putenv('GITHUB_REF_NAME=my-feature');

        try {
            $this->assertSame('my-feature', (new ActionContext())->getBranch());
        } finally {
            putenv('GITHUB_HEAD_REF');
            putenv('GITHUB_REF_NAME');
        }
    }

    /**
     * @test
     */
    public function it_defaults_to_main_when_no_branch_vars_are_set(): void
    {
        putenv('GITHUB_HEAD_REF');
        putenv('GITHUB_REF_NAME');

        $this->assertSame('main', (new ActionContext())->getBranch());
    }

    /**
     * @test
     */
    public function it_extracts_the_workflow_filename_from_workflow_ref(): void
    {
        putenv('GITHUB_WORKFLOW_REF=owner/repo/.github/workflows/main.yml@refs/heads/main');

        try {
            $this->assertSame('main.yml', (new ActionContext())->getWorkflowName());
        } finally {
            putenv('GITHUB_WORKFLOW_REF');
        }
    }

    /**
     * @test
     */
    public function it_handles_yaml_extension_in_workflow_ref(): void
    {
        putenv('GITHUB_WORKFLOW_REF=owner/repo/.github/workflows/tests.yaml@refs/heads/main');

        try {
            $this->assertSame('tests.yaml', (new ActionContext())->getWorkflowName());
        } finally {
            putenv('GITHUB_WORKFLOW_REF');
        }
    }

    /**
     * @test
     */
    public function it_returns_empty_string_when_workflow_ref_is_not_set(): void
    {
        putenv('GITHUB_WORKFLOW_REF');

        $this->assertSame('', (new ActionContext())->getWorkflowName());
    }
}
