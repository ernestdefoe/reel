import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import ReelModal from './components/ReelModal';

app.initializers.add('ernestdefoe-reel', () => {
  extend(TextEditor.prototype, 'toolbarItems', function (items) {
    if (!app.forum.attribute('reelEnabled')) return;

    items.add(
      'reel',
      <TextEditorButton
        className="Button Button--link ReelButton"
        onclick={() => app.modal.show(ReelModal)}
        title={app.translator.trans('ernestdefoe-reel.forum.button')}
      >
        <span className="ReelButton-label" aria-hidden="true">
          GIF
        </span>
      </TextEditorButton>,
      // Next to the formatting buttons, before upload/emoji controls.
      5
    );
  });
});
