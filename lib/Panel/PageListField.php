<?php

namespace GrommasDietz\KirbyLocale\Panel;

/** Native list-field create dialogs need the same locale extension as the global dialog. */
final class PageListField extends \Kirby\Form\Field\PageListField
{
    public function dialogs(): array
    {
        $dialogs = parent::dialogs();
        $dialogs['create']['action'] = fn () => new PageCreateDialogController(
            parent: $this->parentModel(),
            blueprints: $this->blueprints()
        );
        return $dialogs;
    }
}
