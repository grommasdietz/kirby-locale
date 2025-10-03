export const ensureArray = (value) => (Array.isArray(value) ? value : []);

export const normaliseLocales = (maybeLocales) => {
  return ensureArray(maybeLocales)
    .map((locale) => {
      if (!locale) {
        return null;
      }

      if (typeof locale === "string") {
        return {
          code: locale,
          name: locale,
          group: null,
        };
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

      return {
        code,
        name: name || code,
        group,
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
    add(maybeLocales) {
      normaliseLocales(maybeLocales).forEach((locale) => {
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
        });
      });
    },
    list() {
      return collected.slice();
    },
  };
};

export const getSiteLocales = () =>
  normaliseLocales(window.panel?.$store?.state?.languages?.all);

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

  collector.add(getSiteLocales());

  const panelConfig = window.panel?.config || {};
  const dottedPluginId = pluginId.replace("/", ".");
  const pluginConfig =
    panelConfig?.[pluginId] || panelConfig?.[dottedPluginId] || {};

  collector.add(pluginConfig.locales);
  collector.add(pluginConfig.languages);

  const legacyKeys = [
    `${pluginId}.locales`,
    `${pluginId}.languages`,
    `${dottedPluginId}.locales`,
    `${dottedPluginId}.languages`,
  ];

  legacyKeys.forEach((key) => {
    const value = panelConfig?.[key];

    if (value) {
      collector.add(value);
    }
  });

  collector.add(panelConfig.locales);
  collector.add(panelConfig.languages);

  let apiLocales = [];

  if (window.panel?.api?.get) {
    try {
      const response = await window.panel.api.get(`${pluginId}/locales`);
      apiLocales = normaliseLocales(response?.data || response);
      collector.add(apiLocales);
    } catch (error) {
      if (error?.status !== 404) {
        console.warn(
          `[${pluginId}] Unable to fetch locales via plugin endpoint.`,
          error
        );
      }
    }

    try {
      const response = await window.panel.api.get("languages");
      collector.add(normaliseLocales(response?.data || response));
    } catch (error) {
      console.warn(
        `[${pluginId}] Unable to fetch locales via Panel API.`,
        error
      );
    }
  }

  const catalogPreference = resolveCatalogPreference(pluginConfig, {
    ...panelConfig,
    [pluginId]: panelConfig?.[pluginId],
    [dottedPluginId]: panelConfig?.[dottedPluginId],
  });

  if (catalogPreference && catalogPreference !== true) {
    collector.add(catalogPreference);
  }

  const locales = collector.list();

  if (locales.length === 0) {
    console.warn(
      `[${pluginId}] No locales available. Configure \`grommasdietz.kirby-locales\` or enable the plugin API route.`
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

    options.push({
      value: locale.code,
      text:
        locale.name && locale.name !== locale.code
          ? `${locale.name} (${locale.code})`
          : locale.code,
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
