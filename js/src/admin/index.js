import app from 'flarum/admin/app';

const t = (key, params) => app.translator.trans(`ernestdefoe-reel.admin.${key}`, params);

app.initializers.add('ernestdefoe-reel', () => {
  app.registry
    .for('ernestdefoe-reel')
    .registerPermission({ icon: 'fas fa-film', label: t('permission'), permission: 'reel.use' }, 'start')
    .registerSetting({
      setting: 'ernestdefoe-reel.provider',
      type: 'select',
      label: t('provider'),
      help: t('provider_help'),
      options: { giphy: 'GIPHY', klipy: 'KLIPY' },
      default: 'giphy',
    })
    // Each key field links to the page that issues the key.
    .registerSetting({
      setting: 'ernestdefoe-reel.giphy_key',
      type: 'text',
      label: t('giphy_key'),
      help: (
        <span>
          {t('giphy_key_help')}{' '}
          <a href="https://developers.giphy.com/dashboard/?create=true" target="_blank" rel="noopener">
            developers.giphy.com
          </a>
        </span>
      ),
    })
    .registerSetting({
      setting: 'ernestdefoe-reel.klipy_key',
      type: 'text',
      label: t('klipy_key'),
      help: (
        <span>
          {t('klipy_key_help')}{' '}
          <a href="https://partner.klipy.com/api-keys" target="_blank" rel="noopener">
            partner.klipy.com/api-keys
          </a>
        </span>
      ),
    })
    .registerSetting({
      setting: 'ernestdefoe-reel.rating',
      type: 'select',
      label: t('rating'),
      help: t('rating_help'),
      options: { g: t('rating_g'), pg: t('rating_pg'), 'pg-13': t('rating_pg13'), r: t('rating_r') },
      default: 'pg-13',
    });
});
