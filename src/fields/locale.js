import {
  fetchLocales,
  createLocaleOptions,
  getSiteLocaleCodes,
} from "../utils/locales.js";

const normaliseEmpty = (emptyProp) => {
  if (typeof emptyProp === "string") {
    return { text: emptyProp, value: "" };
  }

  if (emptyProp && typeof emptyProp === "object") {
    return {
      text: emptyProp.text ?? "–",
      value: emptyProp.value ?? "",
    };
  }

  return { text: "–", value: "" };
};

const normaliseValue = (value) => {
  if (typeof value !== "string") {
    return "";
  }

  return value.trim();
};

export const createLocaleField = (defaultPluginId) => ({
  name: "locale-field",
  inheritAttrs: false,
  props: {
    value: {
      type: [String, null],
      default: "",
    },
    label: {
      type: [String, Array],
      default: null,
    },
    help: {
      type: [String, Array],
      default: null,
    },
    hint: {
      type: [String, Array],
      default: null,
    },
    info: {
      type: [String, Array],
      default: null,
    },
    placeholder: {
      type: String,
      default: null,
    },
    required: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    autofocus: {
      type: Boolean,
      default: false,
    },
    after: {
      type: [String, Array],
      default: null,
    },
    before: {
      type: [String, Array],
      default: null,
    },
    width: {
      type: [String, Number],
      default: null,
    },
    columns: {
      type: [String, Number],
      default: null,
    },
    counter: {
      type: [Boolean, Number],
      default: null,
    },
    icon: {
      type: String,
      default: "translate",
    },
    empty: {
      type: [String, Object],
      default: () => ({ text: "–", value: "" }),
    },
    search: {
      type: [Boolean, Number],
      default: null,
    },
    locales: {
      type: Array,
      default: () => [],
    },
    options: {
      type: Array,
      default: () => [],
    },
    preferred: {
      type: Array,
      default: () => [],
    },
    plugin: {
      type: String,
      default: defaultPluginId,
    },
    reset: {
      type: Boolean,
      default: true,
    },
    name: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      internalValue: normaliseValue(this.value),
      internalOptions: [],
      loading: false,
      error: null,
      loadToken: null,
    };
  },
  computed: {
    emptyOption() {
      return normaliseEmpty(this.empty);
    },
    searchable() {
      if (typeof this.search === "boolean") {
        return this.search;
      }

      if (typeof this.search === "number") {
        const enabled = this.internalOptions.filter(
          (option) => option && option.disabled !== true
        ).length;
        return enabled >= this.search;
      }

      const enabled = this.internalOptions.filter(
        (option) => option && option.disabled !== true
      ).length;
      return enabled > 7;
    },
    fieldProps() {
      return {
        label: this.label,
        help: this.help,
        hint: this.hint,
        info: this.info,
        after: this.after,
        before: this.before,
        width: this.width,
        columns: this.columns,
        counter: this.counter,
        disabled: this.disabled,
        required: this.required,
        icon: this.icon,
        name: this.name,
        loading: this.loading,
      };
    },
    inputProps() {
      return {
        name: this.name,
        placeholder: this.placeholder,
        autofocus: this.autofocus,
      };
    },
    preferredCodes() {
      if (Array.isArray(this.preferred) && this.preferred.length) {
        return this.preferred;
      }

      return getSiteLocaleCodes();
    },
    providedOptions() {
      if (Array.isArray(this.options) && this.options.length) {
        return this.options;
      }

      if (Array.isArray(this.locales) && this.locales.length) {
        return createLocaleOptions(
          this.locales,
          this.internalValue,
          this.preferredCodes,
          {
            pluginId: this.plugin,
          }
        );
      }

      return [];
    },
  },
  watch: {
    value(newValue) {
      const normalised = normaliseValue(newValue);

      if (normalised !== this.internalValue) {
        this.internalValue = normalised;
        this.refreshOptions();
      }
    },
    locales: {
      handler() {
        this.refreshOptions();
      },
      deep: true,
    },
    options: {
      handler() {
        this.refreshOptions();
      },
      deep: true,
    },
    preferred: {
      handler() {
        this.refreshOptions();
      },
      deep: true,
    },
    plugin(newPlugin, oldPlugin) {
      if (newPlugin !== oldPlugin) {
        this.refreshOptions();
      }
    },
  },
  created() {
    this.refreshOptions();
  },
  methods: {
    async refreshOptions() {
      const token = Symbol("load");
      this.loadToken = token;
      this.loading = true;

      try {
        if (this.providedOptions.length) {
          this.internalOptions = this.providedOptions;
          this.error = null;
          return;
        }

        const locales = await fetchLocales(this.plugin);
        const options = createLocaleOptions(
          locales,
          this.internalValue,
          this.preferredCodes,
          {
            pluginId: this.plugin,
          }
        );

        if (this.loadToken !== token) {
          return;
        }

        this.internalOptions = options;
        this.error = null;
      } catch (error) {
        if (this.loadToken !== token) {
          return;
        }

        console.error(`[${this.plugin}] Failed to load locale options.`, error);
        this.error = error;
        this.internalOptions = [];
      } finally {
        if (this.loadToken === token) {
          this.loading = false;
        }
      }
    },
    handleInput(value) {
      const normalised = normaliseValue(value);

      if (normalised !== this.internalValue) {
        this.internalValue = normalised;
        this.refreshOptions();
      }

      this.$emit("input", normalised);
      this.$emit("change", normalised);
    },
    handleReset() {
      if (this.internalValue !== "") {
        this.internalValue = "";
        this.refreshOptions();
        this.$emit("input", "");
        this.$emit("change", "");
      }
    },
  },
  template: `
    <k-field
      class="k-locale-field"
      v-bind="fieldProps"
    >
      <k-select-input
        v-bind="inputProps"
        v-bind="$attrs"
        :value="internalValue"
        :options="internalOptions"
        :placeholder="placeholder"
        :empty="emptyOption"
        :search="searchable"
        :icon="icon"
        :disabled="disabled || loading"
        :reset="reset"
        v-on="$listeners"
        @input="handleInput"
        @reset="handleReset"
      />
    </k-field>
  `,
});
