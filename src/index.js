import { createLocaleMark } from "./marks/locale.js";
import { createLocaleField } from "./fields/locale.js";

const pluginId = "grommasdietz/kirby-locale";

const normaliseDialogValue = (value) =>
  typeof value === "string" ? value.trim() : "";

const buildLocaleField = (currentValue = null, existingField = null) => {
  const baseField = existingField ? { ...existingField } : {};
  delete baseField.type;
  delete baseField.options;
  delete baseField.empty;
  delete baseField.icon;
  delete baseField.default;
  const label = window.panel.$t("grommasdietz.kirby-locale.label");
  const emptyText = window.panel.$t("grommasdietz.kirby-locale.dialog.empty");
  const value = normaliseDialogValue(currentValue);

  const field = {
    ...baseField,
    name: "titleLocale",
    label,
    type: "locale",
    plugin: pluginId,
    empty: baseField.empty ?? { text: emptyText, value: "" },
    search: baseField.search,
    icon: baseField.icon ?? "translate",
    reset: baseField.reset ?? true,
    default: "",
    translate: false,
    value,
  };

  return {
    field,
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
        titleLocale: value,
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

    if (typeof locale === "string") {
      return locale.trim();
    }
  } catch (error) {
    console.warn(`[${pluginId}] Unable to load stored title locale.`, error);
  }

  return "";
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
  fields: {
    locale: createLocaleField(pluginId),
  },
  dialogs: {
    async "page.create"(dialog) {
      const currentValue = dialog?.props?.value?.titleLocale ?? null;
      const existingField = dialog?.props?.fields?.titleLocale ?? null;

      if (!existingField) {
        return dialog;
      }

      const { field, value } = buildLocaleField(currentValue, existingField);

      return injectLocaleField(dialog, field, value);
    },
    async "page.changeTitle"(dialog, context = {}) {
      const pageId = resolveContextPageId(context);
      const language = resolveContextLanguage(context);
      const existingField = dialog?.props?.fields?.titleLocale ?? null;

      if (!existingField) {
        return dialog;
      }

      let currentValue = dialog?.props?.value?.titleLocale ?? null;

      if (currentValue === null || currentValue === undefined) {
        currentValue = await fetchStoredLocale(pageId, language);
      }

      const { field, value } = buildLocaleField(currentValue, existingField);

      return injectLocaleField(dialog, field, value);
    },
  },
});
