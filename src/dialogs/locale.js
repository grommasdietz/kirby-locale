import { createLocaleOptions, getSiteLocaleCodes } from "../utils/locales.js";

const translate = (pluginId, key, fallback) => {
  const fullKey = `${pluginId.replace("/", ".")}.${key}`;

  if (typeof window.panel?.$t === "function") {
    const translated = window.panel.$t(fullKey);

    if (translated !== fullKey) {
      return translated;
    }
  }

  return fallback ?? fullKey;
};

const buildDialogProps = (pluginId, value, locales) => {
  const siteLocaleCodes = getSiteLocaleCodes();
  const { options, enabledCount } = createLocaleOptions(
    locales,
    value,
    siteLocaleCodes,
    {
      pluginId,
    }
  );
  const hasOptions = enabledCount > 0;

  const baseField = {
    label: translate(pluginId, "dialog.select.label", "Locale"),
    autofocus: true,
  };

  const field = hasOptions
    ? {
        ...baseField,
        type: "select",
        empty: {
          text: translate(pluginId, "dialog.empty", "–"),
        },
  options,
  searchable: enabledCount > 7,
      }
    : {
        ...baseField,
        type: "text",
        placeholder: translate(
          pluginId,
          "prompt",
          "Enter the locale code (e.g. de, en-GB)"
        ),
      };

  return {
    component: "k-form-dialog",
    props: {
      icon: "translate",
      size: "medium",
      headline: translate(
        pluginId,
        "dialog.headline",
        "Choose locale for selection"
      ),
      cancelButton: true,
      submitButton: true,
      fields: {
        locale: field,
      },
      value: {
        locale: value,
      },
    },
  };
};

export const openLocaleDialog = ({ pluginId, value = "", locales = [] }) => {
  if (!window.panel?.dialog?.open) {
    console.warn(
      `[${pluginId}] Kirby Panel dialog API is not available. Cannot open locale dialog.`
    );
    return Promise.resolve(null);
  }

  const dialog = buildDialogProps(pluginId, value, locales);

  return new Promise((resolve) => {
    let settled = false;

    const settle = (result) => {
      if (settled) {
        return;
      }

      settled = true;

      if (typeof window.panel?.dialog?.close === "function") {
        window.panel.dialog.close();
      }

      resolve(result);
    };

    const handleSubmit = (formValue) => {
      const raw = formValue?.locale;

      if (typeof raw !== "string") {
        settle(null);
        return;
      }

      const trimmed = raw.trim();

      if (!trimmed || trimmed === "__separator__") {
        settle(null);
        return;
      }

      settle(trimmed);
    };

    const handleCancel = () => {
      settle(false);
    };

    try {
      const maybePromise = window.panel.dialog.open(dialog, {
        on: {
          submit: handleSubmit,
          cancel: handleCancel,
          close: handleCancel,
        },
      });

      if (typeof maybePromise?.catch === "function") {
        maybePromise.catch((error) => {
          console.error(`[${pluginId}] Failed to open locale dialog.`, error);
          handleCancel();
        });
      }
    } catch (error) {
      console.error(`[${pluginId}] Failed to open locale dialog.`, error);
      handleCancel(error);
    }
  });
};
