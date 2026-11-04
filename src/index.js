import { h } from "vue";
import { createLocaleMark } from "./marks/locale.js";

const pluginId = "grommasdietz/locale";

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
            disabled: option?.disabled === true,
            value: option?.value ?? "",
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
    render() {
      const hostData = {
        class: ["k-select-input", this.$attrs.class],
        "data-disabled": this.disabled,
        "data-empty": this.isEmpty,
        style: this.$attrs.style,
      };

      const rawAttrs = { ...this.$attrs };
      delete rawAttrs.class;
      delete rawAttrs.style;

      const selectData = {
        ...rawAttrs,
        id: this.id,
        autofocus: this.autofocus,
        "aria-label": this.ariaLabel,
        disabled: this.disabled,
        name: this.name,
        required: this.required,
        class: "k-select-input-native",
        value: this.value,
        multiple: this.multiple === true,
        onChange: event => this.__emitSelectValue(event),
        onClick: event => this.onClick(event),
        ref: "input",
      };

      const optionNodes = [];

      if (this.hasEmptyOption) {
        optionNodes.push(
          h(
            "option",
            {
              disabled: this.required === true,
              value: "",
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
              label: group.label,
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

const findNativeSelectComponent = () => window.panel?.app?.component("k-select-input");

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

    window.panel.app.component("k-select-input", enhancedComponent);

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

window.panel.plugin(pluginId, {
  writerMarks: { locale: createLocaleMark(pluginId) },
});

ensureEnhancedSelectComponent();
