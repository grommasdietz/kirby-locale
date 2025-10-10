import { createLocaleMark } from "./marks/locale.js";

const pluginId = "grommasdietz/kirby-locale";
const FIELD_NAME = "title_locale";

const normaliseDialogValue = (value) =>
  typeof value === "string" ? value.trim() : "";

const readLocaleValue = (collection = {}) =>
  collection && typeof collection === "object"
    ? collection[FIELD_NAME] ?? null
    : null;

const buildLocaleField = (currentValue = null, existingField = null) => {
  const baseField = existingField ? { ...existingField } : {};
  const {
    type: existingType,
    options: existingOptions,
    plugin: existingPlugin,
    empty: existingEmpty,
    icon: existingIcon,
    default: existingDefault,
    ...rest
  } = baseField;
  const label = window.panel.$t("grommasdietz.kirby-locale.label");
  const emptyText = window.panel.$t("grommasdietz.kirby-locale.dialog.empty");
  const value = normaliseDialogValue(currentValue);
  const type = existingType || "select";

  const field = {
    ...rest,
    name: FIELD_NAME,
    label,
    type,
    empty: existingEmpty ?? { text: emptyText, value: "" },
    search: rest.search,
    icon: existingIcon ?? "translate",
    reset: rest.reset ?? true,
    default: existingDefault ?? "",
    translate: false,
    value,
  };

  if (type === "locale") {
    field.plugin = pluginId;
  } else if (existingPlugin) {
    field.plugin = existingPlugin;
  } else {
    delete field.plugin;
  }

  if (existingOptions !== undefined) {
    field.options = existingOptions;
  }

  return {
    field,
    value,
  };
};

const injectLocaleField = (dialog, fieldConfig, value) => {
  if (!dialog?.props?.fields) {
    return dialog;
  }

  const originalFields = dialog.props.fields ?? {};
  const mergedLocaleField = {
    ...(originalFields[FIELD_NAME] || {}),
    ...fieldConfig,
  };

  const nextFields = {
    ...originalFields,
    [FIELD_NAME]: mergedLocaleField,
  };

  const originalValues = dialog.props.value ?? {};
  const nextValues = {
    ...originalValues,
    [FIELD_NAME]: value,
  };

  return {
    ...dialog,
    props: {
      ...dialog.props,
      fields: nextFields,
      value: nextValues,
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
    const locale = data?.[FIELD_NAME];

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
  dialogs: {
    async "page.create"(dialog) {
      const existingField = dialog?.props?.fields?.[FIELD_NAME] ?? null;
      const currentValue = readLocaleValue(dialog?.props?.value);

      if (!existingField) {
        return dialog;
      }

      const { field, value } = buildLocaleField(currentValue, existingField);

      return injectLocaleField(dialog, field, value);
    },
    async "page.changeTitle"(dialog, context = {}) {
      const pageId = resolveContextPageId(context);
      const language = resolveContextLanguage(context);
      const existingField = dialog?.props?.fields?.[FIELD_NAME] ?? null;

      if (!existingField) {
        return dialog;
      }

      let currentValue = readLocaleValue(dialog?.props?.value);

      if (currentValue === null || currentValue === undefined) {
        currentValue = await fetchStoredLocale(pageId, language);
      }

      const { field, value } = buildLocaleField(currentValue, existingField);

      return injectLocaleField(dialog, field, value);
    },
  },
});
