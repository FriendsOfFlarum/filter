import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import extractText from 'flarum/common/utils/extractText';

export default [
  new Extend.Admin()
    // Word filtering
    .customSetting(() => <h2>{app.translator.trans('fof-filter.admin.title')}</h2>, 100)
    .setting(
      () => ({
        setting: 'fof-filter.words',
        type: 'textarea',
        rows: 6,
        label: app.translator.trans('fof-filter.admin.filter_label'),
        placeholder: extractText(app.translator.trans('fof-filter.admin.input.placeholder')),
        help: app.translator.trans('fof-filter.admin.bad_words_help'),
      }),
      90
    )
    .setting(
      () => ({
        setting: 'fof-filter.autoDeletePosts',
        type: 'boolean',
        label: app.translator.trans('fof-filter.admin.input.switch.delete'),
      }),
      80
    )

    // Auto merge
    .customSetting(
      () => (
        <>
          <hr />
          <h2>{app.translator.trans('fof-filter.admin.auto_merge_title')}</h2>
        </>
      ),
      70
    )
    .setting(
      () => ({
        setting: 'fof-filter.autoMergePosts',
        type: 'boolean',
        label: app.translator.trans('fof-filter.admin.input.switch.merge'),
      }),
      60
    )
    .setting(
      () => ({
        setting: 'fof-filter.cooldown',
        type: 'number',
        min: 0,
        label: app.translator.trans('fof-filter.admin.cooldownLabel'),
        help: app.translator.trans('fof-filter.admin.help2'),
      }),
      50
    )

    // Email notification
    .customSetting(
      () => (
        <>
          <hr />
          <h2>{app.translator.trans('fof-filter.admin.input.email_label')}</h2>
          <p className="helpText">{app.translator.trans('fof-filter.admin.input.email_help')}</p>
        </>
      ),
      40
    )
    .setting(
      () => ({
        setting: 'fof-filter.flaggedSubject',
        type: 'text',
        label: app.translator.trans('fof-filter.admin.input.email_subject'),
        placeholder: extractText(app.translator.trans('fof-filter.admin.email.default_subject')),
      }),
      30
    )
    .setting(
      () => ({
        setting: 'fof-filter.flaggedEmail',
        type: 'textarea',
        rows: 4,
        label: app.translator.trans('fof-filter.admin.input.email_body'),
        help: app.translator.trans('fof-filter.admin.email_help'),
        placeholder: extractText(app.translator.trans('fof-filter.admin.email.default_text')),
      }),
      20
    )
    .setting(
      () => ({
        setting: 'fof-filter.emailWhenFlagged',
        type: 'boolean',
        label: app.translator.trans('fof-filter.admin.input.switch.email'),
      }),
      10
    )

    .permission(
      () => ({
        icon: 'fas fa-user-ninja',
        label: app.translator.trans('fof-filter.admin.permission.bypass_filter_label'),
        permission: 'discussion.bypassFoFFilter',
      }),
      'reply'
    ),
];
