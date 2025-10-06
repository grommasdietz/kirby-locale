import { createLocaleMark } from "./marks/locale.js";
import {
  fetchLocales,
  createLocaleOptions,
  getSiteLocaleCodes,
} from "./utils/locales.js";

const pluginId = "grommasdietz/kirby-locale";

const buildLocaleField = (
  locales,
  currentValue = null,
  existingField = null
) => {
  const siteLocaleCodes = getSiteLocaleCodes();
  const options = createLocaleOptions(locales, currentValue, siteLocaleCodes, {
    pluginId,
  });
  const enabledOptions = options.filter((option) => option.disabled !== true);
  const baseField = existingField ? { ...existingField } : {};
  const value =
    typeof currentValue === "string" && currentValue.trim() !== ""
      ? currentValue
      : null;

  return {
    field: {
      ...baseField,
      name: "titleLocale",
      label: window.panel.$t("grommasdietz.kirby-locale.label"),
      type: "select",
      options,
      empty: {
        text: window.panel.$t("grommasdietz.kirby-locale.dialog.empty"),
      },
      search: enabledOptions.length > 7,
      icon: "translate",
      value,
    },
    value,
  };
};

const injectLocaleField = (dialog, fieldConfig, value) => {
  if (!dialog?.props?.fields?.titleLocale) {
    return dialog;
  }

  const originalFields = dialog.props.fields ?? {};
  const mergedLocaleField = {
    ...(originalFields.titleLocale || {}),
    ...fieldConfig,
  };

  return {
    ...dialog,
    props: {
      ...dialog.props,
      fields: {
        ...originalFields,
        titleLocale: mergedLocaleField,
      },
      value: {
        ...dialog.props.value,
        titleLocale: value ?? null,
      },
    },
  };
};

const fetchStoredLocale = async (pageId, language) => {
  if (!pageId || !window.panel?.api?.get) {
    return null;
  }

  try {
    const response = await window.panel.api.get(`${pluginId}/title-locale`, {
      id: pageId,
      language,
    });

    const data = response?.data || response;
    const locale = data?.titleLocale;

    if (typeof locale === "string" && locale.trim()) {
      return locale;
    }
  } catch (error) {
    console.warn(`[${pluginId}] Unable to load stored title locale.`, error);
  }

  return null;
};

const resolveContextLanguage = (context = {}) => {
  if (typeof context.language === "string" && context.language) {
    return context.language;
  }

  const current = window.panel?.$store?.state?.languages?.current;
  return typeof current === "string" && current ? current : null;
};

const resolveContextPageId = (context = {}) => {
  if (context?.page?.id) {
    return context.page.id;
  }

  if (context?.model?.id) {
    return context.model.id;
  }

  if (context?.id) {
    return context.id;
  }

  return null;
};

window.panel.plugin(pluginId, {
  writerMarks: {
    locale: createLocaleMark(pluginId),
  },
  dialogs: {
    async "page.create"(dialog) {
      const locales = await fetchLocales(pluginId);
      const currentValue = dialog?.props?.value?.titleLocale ?? null;
      const existingField = dialog?.props?.fields?.titleLocale ?? null;

      if (!existingField) {
        return dialog;
      }

      const { field, value } = buildLocaleField(
        locales,
        currentValue,
        existingField
      );

      return injectLocaleField(dialog, field, value);
    },
    async "page.changeTitle"(dialog, context = {}) {
      const pageId = resolveContextPageId(context);
      const language = resolveContextLanguage(context);
      const locales = await fetchLocales(pluginId);
      const existingField = dialog?.props?.fields?.titleLocale ?? null;

      if (!existingField) {
        return dialog;
      }

      let currentValue = dialog?.props?.value?.titleLocale ?? null;

      if (!currentValue) {
        currentValue = await fetchStoredLocale(pageId, language);
      }

      const { field, value } = buildLocaleField(
        locales,
        currentValue,
        existingField
      );

      return injectLocaleField(dialog, field, value);
    },
  },
});
