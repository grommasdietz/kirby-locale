<?php

namespace GrommasDietz\KirbyLocale\Panel;

use GrommasDietz\KirbyLocale\DialogFactory;
use GrommasDietz\KirbyLocale\LocaleHelper;
use GrommasDietz\KirbyLocale\TitleLocale;
use Kirby\Cms\Page;
use Kirby\Panel\Ui\Dialog;
use Kirby\Panel\Ui\Dialog\FormDialog;

trait TitleLocaleDialog
{
    private function withTitleLocale(Dialog $dialog, string $template, ?Page $page = null): Dialog
    {
        $allowed = LocaleHelper::getEnabledTitleLocaleTemplates($this->kirby);
        if (!$dialog instanceof FormDialog || !LocaleHelper::templateAllowsLocale($template, $allowed)) {
            return $dialog;
        }
        [$hasValue, $rawValue] = LocaleHelper::extractLocaleFromRequest($this->request);
        $language = LocaleHelper::resolveLanguageCode($this->kirby, $this->request->get('language'));
        $value = $hasValue ? LocaleHelper::normaliseTitleLocale($rawValue) : null;
        if ($value === null && $page !== null) {
            $value = TitleLocale::getStored($page, $language);
        }
        $dialog->fields[TitleLocale::FIELD_KEY] = DialogFactory::buildField($value);
        $dialog->value[TitleLocale::FIELD_KEY] = $value ?? '';
        return $dialog;
    }
}
