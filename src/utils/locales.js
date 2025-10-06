import isoTranslations from "./isoTranslations.json";

export const ensureArray = (value) => (Array.isArray(value) ? value : []);

export const normaliseLocales = (maybeLocales, defaultSource = "unknown") => {
  return ensureArray(maybeLocales)
    .map((locale) => {
      if (!locale) {
        return null;
      }

      if (typeof locale === "string") {
        const trimmed = locale.trim();

        if (!trimmed) {
          return null;
        }

        return {
          code: trimmed,
          name: trimmed,
          group: null,
          source: defaultSource,
          nameProvided: false,
        };
      }

      if (typeof locale !== "object") {
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

const normaliseLocaleTag = (value) =>
  typeof value === "string"
    ? value.trim().replace(/_/g, "-").replace(/\s+/g, "")
    : "";

const canonicaliseLocale = (value) => {
  const tag = normaliseLocaleTag(value);

  if (!tag) {
    return "";
  }

  if (
    typeof Intl !== "undefined" &&
    typeof Intl.getCanonicalLocales === "function"
  ) {
    try {
      const result = Intl.getCanonicalLocales(tag);

      if (
        Array.isArray(result) &&
        result.length > 0 &&
        typeof result[0] === "string"
      ) {
        return result[0];
      }
    } catch (error) {
      // ignore and fall back to manual handling below
    }
  }

  return tag;
};

const localeCandidateKeys = (value) => {
  const canonical = canonicaliseLocale(value);

  if (!canonical) {
    return [];
  }

  const lowered = canonical.toLowerCase();
  const candidates = [];

  candidates.push(lowered);

  const [base] = lowered.split("-");

  if (base && base !== lowered) {
    candidates.push(base);
  }

  if (lowered === "nb" && base !== "no") {
    candidates.push("no");
  }

  return Array.from(new Set(candidates));
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
  preferredCodes = [],
  settings = {}
) => {
  const pluginIdOption =
    settings && typeof settings === "object" && settings.pluginId
      ? settings.pluginId
      : "grommasdietz/kirby-locale";
  const dottedPluginId = pluginIdOption.replace("/", ".");

  const translatePanel = (key, fallback) => {
    if (typeof window.panel?.$t === "function") {
      const fullKey = `${dottedPluginId}.${key}`;
      const translated = window.panel.$t(fullKey);

      if (translated && translated !== fullKey) {
        return translated;
      }
    }

    return fallback;
  };

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

  const rawPanelLocale = resolvePanelLocale();
  const canonicalPanelLocale =
    canonicaliseLocale(rawPanelLocale) || canonicaliseLocale("en") || "en";
  const candidateLocales = localeCandidateKeys(canonicalPanelLocale);

  let languageDisplayNames = null;

  try {
    languageDisplayNames = new Intl.DisplayNames([canonicalPanelLocale], {
      type: "language",
    });
  } catch (error) {
    languageDisplayNames = null;
  }

  const fallbackIsoName = (code) => {
    if (!code) {
      return null;
    }

    const candidates = candidateLocales.length ? [...candidateLocales] : [];

    if (isoTranslations.en && candidates.includes("en") === false) {
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

    const englishName =
      typeof isoTranslations.en?.[code] === "string"
        ? isoTranslations.en[code]
        : null;

    const normalisedBase =
      typeof baseName === "string" ? baseName.trim().toLowerCase() : "";
    const normalisedCode =
      typeof code === "string" ? code.trim().toLowerCase() : "";
    const normalisedEnglish =
      typeof englishName === "string" ? englishName.trim().toLowerCase() : "";

    const shouldLocalise =
      locale.nameProvided === false ||
      !normalisedBase ||
      (normalisedCode && normalisedBase === normalisedCode) ||
      (normalisedEnglish && normalisedBase === normalisedEnglish);

    if (shouldLocalise) {
      const translated = fallbackIsoName(code);

      if (translated) {
        return translated;
      }

      if (languageDisplayNames) {
        const displayName = languageDisplayNames.of(code);

        if (displayName && displayName !== code) {
          return displayName;
        }
      }
    }

    return baseName;
  };

  const options = [];
  const seen = new Set();
  const preferredSet = new Set(
    preferredCodes.map((code) => localeKey(code)).filter(Boolean)
  );
  const siteGroupLabel = translatePanel("dialog.group.site", "Site languages");
  const otherGroupLabel = translatePanel(
    "dialog.group.other",
    "Other languages"
  );

  const siteLocales = [];
  const remainingLocales = [];

  locales.forEach((locale) => {
    if (!locale || !locale.code) {
      return;
    }

    const code = normaliseLocaleCode(locale.code);
    const key = localeKey(code);

    if (!code || !key) {
      return;
    }

    if (preferredSet.has(key)) {
      siteLocales.push(locale);
    } else {
      remainingLocales.push(locale);
    }
  });

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

  const pushGroupHeading = (label) => {
    const trimmed = typeof label === "string" ? label.trim() : "";

    if (!trimmed) {
      return;
    }

    const key = localeKey(trimmed);

    if (key && key === lastGroupKey) {
      return;
    }

    options.push({
      value: `__group__${key || options.length}`,
      text: trimmed,
      disabled: true,
    });

    lastGroupKey = key;
  };

  if (siteLocales.length) {
    pushGroupHeading(siteGroupLabel);
    siteLocales.forEach((locale) => {
      pushOption(locale);
    });
    lastGroupKey = null;
  }

  if (remainingLocales.length) {
    lastGroupKey = null;

    remainingLocales.forEach((locale) => {
      const rawGroup =
        typeof locale?.group === "string" && locale.group.trim()
          ? locale.group.trim()
          : "";
      const label = rawGroup || otherGroupLabel;

      pushGroupHeading(label);
      pushOption(locale);
    });
  }

  const normalisedCurrent = normaliseLocaleCode(currentValue);

  if (normalisedCurrent) {
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
