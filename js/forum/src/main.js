import { extend, override } from 'flarum/extend';
import app from 'flarum/app';

import PostControls from 'flarum/utils/PostControls';
import CommentPost from 'flarum/components/CommentPost';
import loginPane from 'issyrocks12/filter/loginPane';

app.initializers.add('issyrocks12-filter', () => {
  loginPane();
  override(CommentPost.prototype, 'flagReason', function(original, flag) {
    if (flag.type() === 'issyrocks12-filter.forum.flagger_name') {
      return app.translator.trans('issyrocks12-filter.forum.flagger_name');
    }

    return original(flag);
  });
}, -20);