<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class DocumentTaskActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $documents = 'Runtime/Documents';
        $tasks = 'Runtime/TasksNotices';

        $definitions->add('documents', 'save_template', 'clinic', $documents, [], ['documents:template'], [], 'matrix', 'documents:edit');
        $definitions->add('documents', ['approve_template', 'reject_template', 'save_document', 'confirm_document'], 'clinic', $documents, ['documents:edit'], ['documents:edit']);
        $definitions->add('documents', 'create_document', 'clinic', $documents, ['documents:add'], ['documents:add']);
        $definitions->add('procedures', 'save', 'clinic', $documents, [], ['procedures:write'], [], 'matrix', 'procedures:edit');
        $definitions->add('procedures', 'toggle', 'clinic', $documents, ['procedures:edit'], ['procedures:edit']);

        $definitions->add('tasks', ['__default__', 'create'], 'clinic', $tasks, ['tasks:add'], ['tasks:add']);
        $definitions->add('tasks', ['start', 'release', 'done', 'comment', 'comment_edit', 'comment_delete'], 'clinic', $tasks, ['tasks:edit'], ['tasks:edit']);
        $definitions->add('notices', ['__default__', 'create'], 'clinic', $tasks, ['notices:add'], ['notices:add']);
        $definitions->add('notices', ['support_message', 'ack', 'hide', 'unhide'], 'clinic', $tasks, ['notices:view'], ['notices:self_state'], [], 'notice_support');

        return $definitions->definitions();
    }
}
