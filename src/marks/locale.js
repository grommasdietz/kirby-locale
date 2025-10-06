import { fetchLocales } from '../utils/locales.js';
import { openLocaleDialog } from '../dialogs/locale.js';

export const createLocaleMark = (pluginId) => ({
  get button() {
    return {
      icon: 'translate',
      label: window.panel.$t(`${pluginId.replace('/', '.')}.label`),
    };
  },

  commands() {
    return async () => {
      const currentAttrs = this.editor.getMarkAttrs('locale') || {};
      const currentValue =
        typeof currentAttrs.lang === 'string' ? currentAttrs.lang : '';

      const locales = await fetchLocales(pluginId);
      const result = await openLocaleDialog({
        pluginId,
        value: currentValue,
        locales,
      });

      if (result === false) {
        return;
      }

      if (!result) {
        this.remove();
        return;
      }

      this.update({
        lang: result,
      });
    };
  },

  get name() {
    return 'locale';
  },

  get schema() {
    return {
      attrs: {
        lang: {
          default: null,
        },
      },
      parseDOM: [
        {
          tag: 'span.notranslate[lang]',
          getAttrs: (dom) => {
            const lang = dom.getAttribute('lang');

            return {
              lang,
            };
          },
        },
      ],
      toDOM: (node) => {
        const attrs = {
          class: 'notranslate',
          lang: node.attrs.lang || null,
        };

        return ['span', attrs, 0];
      },
    };
  },
});
