<?php

namespace GrommasDietz\KirbyLocale\Panel;

use GrommasDietz\KirbyLocale\TitleLocale;
use Kirby\Panel\Ui\Dialog;

final class PageChangeTitleDialogController extends \Kirby\Panel\Controller\Dialog\PageChangeTitleDialogController
{
    use TitleLocaleDialog;

    public function load(): Dialog
    {
        return $this->withTitleLocale(parent::load(), $this->page->intendedTemplate()->name(), $this->page);
    }

    public function submit(): array|true
    {
        $response = parent::submit();
        TitleLocale::handlePageLocaleUpdate($this->page);
        return $response;
    }
}
