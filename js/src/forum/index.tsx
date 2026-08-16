/*
 *
 *  This file is part of fof/filter.
 *
 *  Copyright (c) 2020 FriendsOfFlarum..
 *
 *  For the full copyright and license information, please view the license.md
 *  file that was distributed with this source code.
 *
 */

import { override } from 'flarum/common/extend';
import app from 'flarum/forum/app';

import CommentPost from 'flarum/forum/components/CommentPost';
import type Flag from 'ext:flarum/flags/forum/models/Flag';

app.initializers.add(
  'fof-filter',
  () => {
    // `override` rather than `extend`: flags' own `flagReason` returns
    // `undefined` for any type it doesn't know about, and `extend` discards
    // its callback's return value, so there would be nothing to mutate.
    // flarum/approval overrides this same method for the same reason.
    override(CommentPost.prototype, 'flagReason', function (original, flag: Flag) {
      if (flag.type() !== 'autoMod') {
        return original(flag);
      }

      const detail = flag.reasonDetail();

      return [app.translator.trans('fof-filter.forum.flagger_name'), !!detail && <span className="Post-flagged-detail">{detail}</span>];
    });
  },
  -20
);
