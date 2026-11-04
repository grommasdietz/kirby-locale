<?php

namespace GrommasDietz\KirbyLocale\Panel;

use Kirby\Panel\Ui\Dialog;

final class PageCreateDialogController extends \Kirby\Panel\Controller\Dialog\PageCreateDialogController
{
    use TitleLocaleDialog;

    public function load(): Dialog
    {
        return $this->withTitleLocale(parent::load(), $this->template());
    }
}
