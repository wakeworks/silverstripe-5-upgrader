<?php

declare(strict_types=1);

namespace WakeWorks\SilverstripeFiveUpgrader\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class MoveEmailSendIfToTryCatch extends AbstractRector
{
    private static $first = true;

    public function getNodeTypes(): array
    {
        return [If_::class];
    }

    public function refactor(Node $node): ?Node
    {
        var_dump($node);
        die;
        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Move Email::send() from if to try/catch block', [
                new CodeSample(
                    // code before
                    'if($email->send()) { ... }',
                    // code after
                    'try { $email->send(); } catch(TransportExceptionInterface $e) { ... }'
                ),
            ]
        );
    }
}