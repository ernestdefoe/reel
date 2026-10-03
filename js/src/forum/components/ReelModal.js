import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Icon from 'flarum/common/components/Icon';

const t = (key, params) => app.translator.trans(`ernestdefoe-reel.forum.${key}`, params);

/**
 * The picker: trending GIFs first, then results as you type, more as you
 * scroll. Choosing one puts it into the post and closes the picker.
 */
export default class ReelModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.query = '';
    this.items = [];
    this.next = 0;
    this.loading = false;
    this.error = null;
    this.credit = null;
    this.seq = 0;
    this.fetch(true);
  }

  className() {
    return 'ReelModal Modal--large';
  }

  title() {
    return t('title');
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    // Load the next page as the end of the results scrolls into view.
    this.observer = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting) && this.next !== null && !this.loading) this.fetch(false);
    });
    const sentinel = this.element.querySelector('.ReelModal-sentinel');
    if (sentinel) this.observer.observe(sentinel);
    setTimeout(() => this.element.querySelector('.ReelModal-search input')?.focus(), 50);
  }

  onremove(vnode) {
    super.onremove(vnode);
    this.observer?.disconnect();
    clearTimeout(this.timer);
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="ReelModal-search">
          <Icon name="fas fa-magnifying-glass" />
          <input
            className="FormControl"
            type="search"
            placeholder={t('search_placeholder')}
            aria-label={t('search_placeholder')}
            value={this.query}
            oninput={(e) => this.typed(e.target.value)}
            onkeydown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(this.timer);
                this.fetch(true);
              }
            }}
          />
        </div>

        <p className="ReelModal-heading">{this.query.trim() ? t('results_for', { query: this.query.trim() }) : t('trending')}</p>

        {this.error ? <p className="ReelModal-error">{this.error}</p> : null}

        {!this.error && !this.loading && this.items.length === 0 ? <p className="ReelModal-empty">{t('no_results')}</p> : null}

        <div className="ReelModal-grid">
          {this.columns().map((column) => (
            <div className="ReelModal-column">
              {column.map((gif) => (
                <button type="button" className="ReelModal-item" key={gif.id} title={gif.title} onclick={() => this.choose(gif)}>
                  <img
                    src={gif.thumb}
                    alt={gif.title || t('untitled')}
                    loading="lazy"
                    width={gif.thumbWidth}
                    height={gif.thumbHeight}
                    style={{ aspectRatio: `${gif.thumbWidth} / ${gif.thumbHeight}` }}
                  />
                </button>
              ))}
            </div>
          ))}
        </div>

        <div className="ReelModal-sentinel">{this.loading ? <LoadingIndicator size="small" display="inline" /> : null}</div>

        {this.credit ? <p className="ReelModal-credit">{t('powered_by', { provider: this.credit })}</p> : null}
      </div>
    );
  }

  /**
   * GIFs placed one at a time into whichever column is shortest so far.
   *
   * 🚨 Not CSS columns. Those fill DOWN each column and rebalance whenever the
   * list grows, so loading the next page moved every GIF already on screen —
   * the one under your cursor jumped away as you scrolled. Placing in order by
   * height means earlier GIFs never move, and the order reads left to right.
   */
  columns() {
    const count = window.innerWidth <= 600 ? 2 : 3;
    const columns = Array.from({ length: count }, () => []);
    const heights = new Array(count).fill(0);

    this.items.forEach((gif) => {
      const shortest = heights.indexOf(Math.min(...heights));
      columns[shortest].push(gif);
      heights[shortest] += (gif.thumbHeight || 1) / (gif.thumbWidth || 1);
    });

    return columns;
  }

  typed(value) {
    this.query = value;
    clearTimeout(this.timer);
    // Wait for a pause in typing: one search per word, not per keystroke.
    this.timer = setTimeout(() => this.fetch(true), 350);
  }

  fetch(fresh) {
    const seq = ++this.seq;
    if (fresh) {
      this.items = [];
      this.next = 0;
    }
    this.loading = true;
    this.error = null;
    m.redraw();

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/reel/search',
        params: { q: this.query.trim(), offset: this.next || 0 },
        errorHandler: () => {},
      })
      .then((r) => {
        // A slower, older search must not overwrite a newer one.
        if (seq !== this.seq) return;
        this.items = fresh ? r.items : this.items.concat(r.items);
        this.next = r.next;
        this.credit = r.credit;
      })
      .catch((e) => {
        if (seq !== this.seq) return;
        this.error = e?.response?.errors?.[0]?.detail || t(e?.status === 429 ? 'slow_down' : 'failed');
      })
      .finally(() => {
        if (seq !== this.seq) return;
        this.loading = false;
        m.redraw();
      });
  }

  choose(gif) {
    const format = app.forum.attribute('reelInsertFormat');
    // Square brackets would end Markdown's alt text early.
    const alt = (gif.title || 'GIF').replace(/[[\]]/g, '');
    const text = format === 'markdown' ? `![${alt}](${gif.url})` : format === 'bbcode' ? `[img]${gif.url}[/img]` : gif.url;

    app.composer.editor?.insertAtCursor(text, false);
    this.hide();
  }
}
