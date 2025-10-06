import isoTranslations from "./isoTranslations.json";

export const ensureArray = (value) => (Array.isArray(value) ? value : []);

export const normaliseLocales = (maybeLocales, defaultSource = "unknown") => {
  return ensureArray(maybeLocales)
    .map((locale) => {
      if (!locale || typeof locale !== "object") {
        return null;
      }

      const code =
        locale.code || locale.id || locale.value || locale.slug || locale.name;

      const name = locale.name || locale.label || locale.code;
      const groupCandidate =
        locale.group || locale.continent || locale.region || locale.category;
      const group =
        typeof groupCandidate === "string" && groupCandidate.trim()
          ? groupCandidate.trim()
          : null;

      if (!code) {
        return null;
      }

      const source =
        typeof locale.source === "string" && locale.source.trim()
          ? locale.source.trim()
          : defaultSource;

      const nameProvided =
        typeof locale.nameProvided === "boolean"
          ? locale.nameProvided
          : source !== "catalog" &&
            Object.prototype.hasOwnProperty.call(locale, "name");

      return {
        code,
        name: name || code,
        group,
        source,
        nameProvided,
      };
    })
    .filter((locale) => locale && locale.code);
};

export const normaliseLocaleCode = (code) =>
  typeof code === "string" ? code.trim() : "";

export const localeKey = (code) => {
  const normalised = normaliseLocaleCode(code);
  return normalised ? normalised.toLowerCase() : "";
};

export const createLocaleCollector = () => {
  const collected = [];
  const seen = new Set();

  return {
    add(maybeLocales, source) {
      normaliseLocales(maybeLocales, source).forEach((locale) => {
        if (!locale || !locale.code) {
          return;
        }

        const code = normaliseLocaleCode(locale.code);
        const key = localeKey(code);

        if (!code || !key || seen.has(key)) {
          return;
        }

        seen.add(key);

        collected.push({
          code,
          name: locale.name && locale.name !== code ? locale.name : code,
          group:
            typeof locale.group === "string" && locale.group.trim()
              ? locale.group.trim()
              : null,
          source: locale.source || source || "unknown",
          nameProvided: Boolean(locale.nameProvided),
        });
      });
    },
    list() {
      return collected.slice();
    },
  };
};

export const getSiteLocales = () =>
  normaliseLocales(
    window.panel?.$store?.state?.languages?.all,
    "site-language"
  );

export const getSiteLocaleCodes = () =>
  getSiteLocales()
    .map((locale) => locale.code)
    .filter(Boolean);

const localeCache = new Map();

export const clearLocaleCache = () => {
  localeCache.clear();
};

const resolveCatalogPreference = (pluginConfig = {}, panelConfig = {}) => {
  const candidateKeys = [
    "catalog",
    "catalogLocales",
    "localeCatalog",
    "fallbackLocales",
    "defaultLocales",
    "builtinLocales",
  ];

  const search = (config) => {
    for (const key of candidateKeys) {
      if (Object.prototype.hasOwnProperty.call(config, key)) {
        return config[key];
      }
    }

    return undefined;
  };

  const pluginValue = search(pluginConfig);

  if (pluginValue !== undefined) {
    return pluginValue;
  }

  const panelValue = search(panelConfig);

  if (panelValue !== undefined) {
    return panelValue;
  }

  return undefined;
};

export const fetchLocales = async (pluginId) => {
  if (!pluginId) {
    throw new Error("fetchLocales requires a plugin id");
  }

  if (localeCache.has(pluginId)) {
    return localeCache.get(pluginId);
  }

  const collector = createLocaleCollector();

  const panelConfig = window.panel?.config || {};
  const dottedPluginId = pluginId.replace("/", ".");
  const pluginConfig =
    panelConfig?.[pluginId] || panelConfig?.[dottedPluginId] || {};
  let fetchedFromApi = false;

  if (window.panel?.api?.get) {
    try {
      const response = await window.panel.api.get(`${pluginId}/locales`);
      const apiLocales = normaliseLocales(response?.data || response);

      if (apiLocales.length) {
        collector.add(apiLocales);
        fetchedFromApi = true;
      }
    } catch (error) {
      if (error?.status !== 404) {
        console.warn(
          `[${pluginId}] Unable to fetch locales via plugin endpoint.`,
          error
        );
      }
    }
  }

  if (fetchedFromApi === false) {
    collector.add(getSiteLocales());

    const catalogPreference = resolveCatalogPreference(pluginConfig, {
      ...panelConfig,
      [pluginId]: panelConfig?.[pluginId],
      [dottedPluginId]: panelConfig?.[dottedPluginId],
    });

    if (catalogPreference && catalogPreference !== true) {
      collector.add(catalogPreference, "catalog");
    }
  }

  const locales = collector.list();

  if (locales.length === 0) {
    console.warn(
      `[${pluginId}] No locales available. Configure \`grommasdietz.kirby-locale.locales\` or enable the plugin API route.`
    );
  }

  localeCache.set(pluginId, locales);

  return locales;
};

export const createLocaleOptions = (
  locales = [],
  currentValue = null,
  preferredCodes = []
) => {
  const resolvePanelLocale = () => {
    const direct = window.panel?.config?.translation;

    if (typeof direct === "string" && direct) {
      return direct;
    }

    const storeTranslation = window.panel?.$store?.state?.translation;

    if (storeTranslation) {
      if (
        typeof storeTranslation === "string" &&
        storeTranslation.trim() !== ""
      ) {
        return storeTranslation;
      }

      if (
        typeof storeTranslation === "object" &&
        typeof storeTranslation.code === "string" &&
        storeTranslation.code.trim() !== ""
      ) {
        return storeTranslation.code;
      }
    }

    return navigator.language || "en";
  };

  const panelLocale = resolvePanelLocale();

  let languageDisplayNames = null;

  try {
    languageDisplayNames = new Intl.DisplayNames([panelLocale], {
      type: "language",
    });
  } catch (error) {
    languageDisplayNames = null;
  }

  const fallbackIsoName = (code) => {
    if (!code) {
      return null;
    }

    const candidates = [];

    if (typeof panelLocale === "string" && panelLocale.trim() !== "") {
      const lower = panelLocale.toLowerCase();

      if (isoTranslations[lower]) {
        candidates.push(lower);
      }

      const base = lower.split("-")[0];

      if (base && isoTranslations[base] && base !== lower) {
        candidates.push(base);
      }
    }

    if (isoTranslations.en) {
      candidates.push("en");
    }

    for (const candidate of candidates) {
      const translated = isoTranslations[candidate]?.[code];

      if (translated) {
        return translated;
      }
    }

    return null;
  };

  const resolveName = (locale, code) => {
    const baseName =
      typeof locale.name === "string" && locale.name.trim()
        ? locale.name.trim()
        : code;

    if (locale.source === "catalog" && locale.nameProvided === false) {
      if (languageDisplayNames) {
        const displayName = languageDisplayNames.of(code);

        if (displayName && displayName !== code) {
          return displayName;
        }
      }

      const translated = fallbackIsoName(code);

      if (translated) {
        return translated;
      }
    }

    return baseName;
  };

  const options = [];
  const seen = new Set();
  const preferredSet = new Set(
    preferredCodes.map((code) => localeKey(code)).filter(Boolean)
  );

  const groupKey = (label) =>
    typeof label === "string" && label.trim() ? localeKey(label) : "";

  const groupLabel = (locale) => {
    const raw = locale?.group;
    return typeof raw === "string" && raw.trim() ? raw.trim() : "";
  };

  const pushOption = (locale) => {
    if (!locale || !locale.code) {
      return;
    }

    const code = normaliseLocaleCode(locale.code);
    const key = localeKey(code);

    if (!code || !key || seen.has(key)) {
      return;
    }

    seen.add(key);

    const label = resolveName(locale, code);

    options.push({
      value: locale.code,
      text: label && label !== code ? `${label} (${locale.code})` : locale.code,
    });
  };

  let lastGroupKey = null;

  const appendLocales = (items, resetGroup = false) => {
    if (resetGroup) {
      lastGroupKey = null;
    }

    items.forEach((locale) => {
      const label = groupLabel(locale);
      const key = label ? groupKey(label) : "";

      if (label && key !== lastGroupKey) {
        options.push({
          value: `__group__${key || options.length}`,
          text: label,
          disabled: true,
        });
        lastGroupKey = key;
      }

      if (!label) {
        lastGroupKey = null;
      }

      pushOption(locale);
    });
  };

  const preferredLocales = locales.filter((locale) =>
    preferredSet.has(localeKey(locale.code))
  );
  const otherLocales = locales.filter(
    (locale) => preferredSet.has(localeKey(locale.code)) === false
  );

  appendLocales(preferredLocales, true);

  if (preferredLocales.length && otherLocales.length) {
    options.push({
      value: "__separator__",
      text: "──────────",
      disabled: true,
    });
    lastGroupKey = null;
  }

  appendLocales(otherLocales, true);

  const normalisedCurrent = normaliseLocaleCode(currentValue);

  if (normalisedCurrent && normalisedCurrent !== "__separator__") {
    const currentKey = localeKey(normalisedCurrent);

    if (currentKey && seen.has(currentKey) === false) {
      options.unshift({
        value: currentValue,
        text: currentValue,
      });
      seen.add(currentKey);
    }
  }

  return options;
};
