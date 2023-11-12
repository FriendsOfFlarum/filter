import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';

export default class FilterSettingsPage extends ExtensionPage {
  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
  }

  content() {
    return (
      <div className="FilterSettingsPage">
        <div className="container">
          <form>
            <h2>{app.translator.trans('fof-filter.admin.title')}</h2>
            {this.buildSettingComponent({
              type: 'textarea',
              rows: 6,
              setting: 'fof-filter.words',
              label: app.translator.trans('fof-filter.admin.filter_label'),
              placeholder: app.translator.trans('fof-filter.admin.input.placeholder'),
              help: app.translator.trans('fof-filter.admin.bad_words_help'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-filter.autoDeletePosts',
              label: app.translator.trans('fof-filter.admin.input.switch.delete'),
            })}
            <hr />
            <h2>{app.translator.trans('fof-filter.admin.auto_merge_title')}</h2>
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-filter.autoMergePosts',
              label: app.translator.trans('fof-filter.admin.input.switch.merge'),
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-filter.cooldown',
              label: app.translator.trans('fof-filter.admin.cooldownLabel'),
              help: app.translator.trans('fof-filter.admin.help2'),
              min: 0,
            })}
            <hr />
            <h2>{app.translator.trans('fof-filter.admin.input.email_label')}</h2>
            {this.buildSettingComponent({
              type: 'string',
              setting: 'fof-filter.flaggedSubject',
              label: app.translator.trans('fof-filter.admin.input.email_subject'),
              placeholder: app.translator.trans('fof-filter.admin.email.default_subject'),
            })}
            {this.buildSettingComponent({
              type: 'textarea',
              rows: 4,
              setting: 'fof-filter.flaggedEmail',
              label: app.translator.trans('fof-filter.admin.input.email_body'),
              help: app.translator.trans('fof-filter.admin.email_help'),
              placeholder: app.translator.trans('fof-filter.admin.email.default_text'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-filter.emailWhenFlagged',
              label: app.translator.trans('fof-filter.admin.input.switch.email'),
            })}
            <hr />
            {this.submitButton()}
          </form>
        </div>
      </div>
    );
  }
}
