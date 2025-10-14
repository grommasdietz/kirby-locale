import { createLocaleMark } from "./marks/locale.js";

const pluginId = "grommasdietz/kirby-locale";
const FIELD_NAME = "title_locale";

const buildGroupedSelectComponent = (selectComponent) => {
  if (!selectComponent || selectComponent.__localeGroupsEnabled) {
    return null;
  }

  const normaliseGroupLabel = (option) => {
    if (!option || typeof option !== "object") {
      return null;
    }

    const raw = option.group;

    if (typeof raw !== "string") {
      return null;
    }

    const trimmed = raw.trim();
    return trimmed === "" ? null : trimmed;
  };

  const enhanceOptionText = (option) => {
    if (!option || typeof option !== "object") {
      return "";
    }

    const text = option.text;

    if (typeof text === "string" && text.trim() !== "") {
      return text;
    }

    return typeof option.value === "string"
      ? option.value
      : String(option.value ?? "");
  };

  const component = {
    extends: selectComponent,
    computed: {
      __localeOptionBuckets() {
        const source = Array.isArray(this.options) ? this.options : [];
        const ungrouped = [];
        const groupedMap = new Map();
        const orderedGroups = [];

        for (const option of source) {
          const groupLabel = normaliseGroupLabel(option);

          if (!groupLabel) {
            ungrouped.push(option);
            continue;
          }

          if (groupedMap.has(groupLabel) === false) {
            groupedMap.set(groupLabel, []);
            orderedGroups.push(groupLabel);
          }

          groupedMap.get(groupLabel).push(option);
        }

        const grouped = orderedGroups.map((label) => ({
          label,
          options: groupedMap.get(label) ?? [],
        }));

        return {
          ungrouped,
          grouped,
        };
      },
    },
    methods: {
      __renderOptionVNode(h, option, keyPrefix) {
        return h(
          "option",
          {
            attrs: {
              disabled: option?.disabled === true,
              value: option?.value ?? "",
            },
            key: `${keyPrefix}-${option?.value ?? ""}`,
          },
          enhanceOptionText(option)
        );
      },
      __emitSelectValue(event) {
        if (this.multiple === true) {
          const selectedOptions = Array.from(
            event?.target?.selectedOptions ?? []
          ).map((option) => option.value);

          this.$emit("input", selectedOptions);
          return;
        }

        this.$emit("input", event?.target?.value ?? "");
      },
    },
    render(h) {
      const hostData = {
        class: ["k-select-input", this.$attrs.class],
        attrs: {
          "data-disabled": this.disabled,
          "data-empty": this.isEmpty,
        },
        style: this.$attrs.style,
      };

      const rawAttrs = { ...this.$attrs };
      delete rawAttrs.class;
      delete rawAttrs.style;

      const selectData = {
        attrs: {
          ...rawAttrs,
          id: this.id,
          autofocus: this.autofocus,
          "aria-label": this.ariaLabel,
          disabled: this.disabled,
          name: this.name,
          required: this.required,
        },
        class: "k-select-input-native",
        domProps: {
          value: this.multiple === true ? undefined : this.value,
        },
        on: {
          change: (event) => this.__emitSelectValue(event),
          click: (event) => this.onClick(event),
        },
        ref: "input",
      };

      if (this.multiple === true) {
        selectData.attrs.multiple = true;

        if (Array.isArray(this.value)) {
          selectData.domProps.value = this.value;
        }
      }

      const optionNodes = [];

      if (this.hasEmptyOption) {
        optionNodes.push(
          h(
            "option",
            {
              attrs: {
                disabled: this.required === true,
                value: "",
              },
              key: "__empty__",
            },
            this.empty
          )
        );
      }

      const buckets = this.__localeOptionBuckets;

      buckets.ungrouped.forEach((option, index) => {
        optionNodes.push(this.__renderOptionVNode(h, option, `plain-${index}`));
      });

      buckets.grouped.forEach((group, groupIndex) => {
        const groupOptions = group.options.map((option, optionIndex) =>
          this.__renderOptionVNode(
            h,
            option,
            `group-${groupIndex}-${optionIndex}`
          )
        );

        optionNodes.push(
          h(
            "optgroup",
            {
              attrs: {
                label: group.label,
              },
              key: `group-${groupIndex}`,
            },
            groupOptions
          )
        );
      });

      const selectNode = h("select", selectData, optionNodes);

      return h("span", hostData, [selectNode, this.label]);
    },
  };

  component.__localeGroupsEnabled = true;

  return component;
};

const findNativeSelectComponent = () => {
  const directMatch =
    window.panel?.$components?.["k-select-input"] ??
    window.panel?.components?.["k-select-input"] ??
    window.panel?.app?.$components?.["k-select-input"] ??
    window.panel?.app?.$vue?.$options?.components?.["k-select-input"] ??
    window.panel?.app?.$vue?.options?.components?.["k-select-input"];

  if (directMatch) {
    return directMatch;
  }

  const maybeVue = window.panel?.app?.$vue || window.panel?.Vue || window.Vue;

  if (maybeVue && typeof maybeVue.component === "function") {
    const resolved = maybeVue.component("k-select-input");

    if (resolved) {
      return resolved;
    }
  }

  return null;
};

const applyGroupedSelectComponent = () => {
  try {
    const baseComponent = findNativeSelectComponent();

    if (!baseComponent) {
      return false;
    }

    if (baseComponent.__localeGroupsEnabled) {
      return true;
    }

    const enhancedComponent = buildGroupedSelectComponent(baseComponent);

    if (!enhancedComponent) {
      return false;
    }

    if (window.panel) {
      window.panel.$components = window.panel.$components || {};
      window.panel.$components["k-select-input"] = enhancedComponent;
    }

    const maybeVue =
      window.panel?.app?.$vue || window.panel?.Vue || window.Vue;

    if (maybeVue && typeof maybeVue.component === "function") {
      maybeVue.component("k-select-input", enhancedComponent);
    }

    Object.defineProperty(baseComponent, "__localeGroupsEnabled", {
      value: true,
      configurable: true,
      writable: true,
    });

    return true;
  } catch (error) {
    console.warn(
      `[${pluginId}] Unable to enhance k-select-input with optgroup support.`,
      error
    );
    return false;
  }
};

const ensureEnhancedSelectComponent = (() => {
  let attempts = 0;
  let running = false;
  const maxAttempts = 60;
  const delay = 50;

  const step = () => {
    const success = applyGroupedSelectComponent();

    if (success) {
      running = false;
      return;
    }

    attempts += 1;

    if (attempts >= maxAttempts) {
      running = false;
      console.warn(
        `[${pluginId}] Timed out while waiting for k-select-input to become available.`
      );
      return;
    }

    setTimeout(step, delay);
  };

  return () => {
    if (running) {
      return;
    }

    attempts = 0;
    running = true;
    setTimeout(step, 0);
  };
})();

const normaliseDialogValue = (value) =>
  typeof value === "string" ? value.trim() : "";

const readLocaleValue = (collection = {}) =>
  collection && typeof collection === "object"
    ? collection[FIELD_NAME] ?? null
    : null;

const buildLocaleField = (currentValue = null, existingField = null) => {
  ensureEnhancedSelectComponent();

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
const pluginConfig = {
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
};

window.panel.plugin(pluginId, pluginConfig);

ensureEnhancedSelectComponent();
