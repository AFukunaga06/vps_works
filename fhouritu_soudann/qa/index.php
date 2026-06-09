<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>法律Q&A（AIアシスタント）| ABC○○法律事務所相談窓口</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root {
  --fuku-green: #3a7d5c;
  --fuku-green-dark: #2c5f44;
  --fuku-light: #e8f5ee;
  --legal-color: #4a148c;
  --legal-bg: #f3e5f5;
}
body {
  font-family: 'Hiragino Sans', 'Meiryo', 'Yu Gothic', sans-serif;
  background: #f8f9fa;
  color: #333;
}
.site-header {
  background: var(--fuku-green);
  color: #fff;
  padding: 1.5rem 1rem;
  text-align: center;
}
.site-header h1 { font-size: 1.4rem; font-weight: bold; margin-bottom: .3rem; }
.site-header p  { font-size: .9rem; opacity: .9; margin: 0; }
.back-link { color: rgba(255,255,255,.8); font-size: .85rem; text-decoration: none; }
.back-link:hover { color: #fff; }

/* チャットエリア */
#chat-box {
  height: 420px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 10px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .75rem;
}
.msg { display: flex; gap: .6rem; max-width: 88%; }
.msg.user  { align-self: flex-end; flex-direction: row-reverse; }
.msg.ai    { align-self: flex-start; }

.bubble {
  padding: .6rem .9rem;
  border-radius: 14px;
  font-size: .93rem;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}
.msg.user .bubble {
  background: var(--fuku-green);
  color: #fff;
  border-bottom-right-radius: 4px;
}
.msg.ai .bubble {
  background: var(--fuku-light);
  color: #333;
  border-bottom-left-radius: 4px;
}
.msg.error .bubble {
  background: #fdecea;
  color: #c62828;
}
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  font-weight: bold;
  flex-shrink: 0;
  margin-top: 2px;
}
.msg.user .avatar { background: var(--fuku-green-dark); color: #fff; }
.msg.ai   .avatar { background: var(--legal-bg); color: var(--legal-color); }

/* 入力エリア */
#input-area { display: flex; gap: .5rem; margin-top: .75rem; }
#q-input {
  flex: 1;
  border-radius: 22px;
  border: 1px solid #ced4da;
  padding: .55rem 1rem;
  font-size: .93rem;
  resize: none;
  font-family: inherit;
  line-height: 1.5;
}
#q-input:focus { outline: none; border-color: var(--fuku-green); box-shadow: 0 0 0 3px rgba(58,125,92,.15); }
#send-btn {
  background: var(--fuku-green);
  color: #fff;
  border: none;
  border-radius: 22px;
  padding: .55rem 1.3rem;
  font-size: .93rem;
  font-weight: bold;
  cursor: pointer;
  transition: .2s;
  white-space: nowrap;
}
#send-btn:hover:not(:disabled) { background: var(--fuku-green-dark); }
#send-btn:disabled { opacity: .6; cursor: not-allowed; }

/* 免責・注意書き */
.notice-box {
  background: #fff8e1;
  border-left: 4px solid #f9a825;
  border-radius: 4px;
  padding: .7rem 1rem;
  font-size: .82rem;
  line-height: 1.6;
}

/* タイピングインジケータ */
.typing-dots span {
  display: inline-block;
  width: 7px; height: 7px;
  background: var(--legal-color);
  border-radius: 50%;
  margin: 0 2px;
  animation: bounce .9s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: .15s; }
.typing-dots span:nth-child(3) { animation-delay: .30s; }
@keyframes bounce {
  0%,80%,100% { transform: translateY(0); }
  40%         { transform: translateY(-6px); }
}

footer { background: #2c3e35; color: #aaa; font-size: .8rem; padding: 1.2rem; text-align: center; margin-top: 2rem; }
</style>
</head>
<body>

<div class="site-header">
  <a href="../index.php" class="back-link">← トップページへ戻る</a>
  <h1>法律Q&A（AIアシスタント）</h1>
  <p>法律に関する一般的な疑問にAIがお答えします</p>
</div>

<div class="container py-3" style="max-width: 760px;">

  <!-- 注意書き -->
  <div class="notice-box mb-3">
    <strong>ご利用にあたって：</strong>
    このAIアシスタントは、法律に関する一般的な情報をお伝えするものです。
    個別案件への法的判断・代理交渉・訴訟対応は行いません。
    具体的なご相談は、<a href="../reserve/index.php" style="color:var(--fuku-green)">専門家への相談予約（初回無料）</a>をご利用ください。
  </div>

  <!-- チャットボックス -->
  <div id="chat-box">
    <div class="msg ai">
      <div class="avatar">AI</div>
      <div class="bubble">こんにちは！ABC○○法律事務所相談窓口のAIアシスタントです。
相続・離婚・契約・労働問題など、法律に関するご質問をどうぞ。
専門家への相談前の「整理」のお手伝いをします。</div>
    </div>
  </div>

  <!-- 入力エリア -->
  <div id="input-area">
    <textarea id="q-input" rows="2" placeholder="質問を入力してください（例：相続放棄はいつまでにすればよいですか）"></textarea>
    <button id="send-btn" onclick="sendQuestion()">送信</button>
  </div>
  <div class="text-end mt-1">
    <small class="text-muted" id="char-count">0 / 1000文字</small>
    &nbsp;
    <a href="#" onclick="clearChat(); return false;" class="text-muted" style="font-size:.82rem;">会話をリセット</a>
  </div>

  <!-- 予約へのリンク -->
  <div class="text-center mt-4">
    <p class="small text-muted mb-2">さらに詳しく相談したい方は、専門家への予約をどうぞ</p>
    <a href="../reserve/index.php" class="btn btn-success px-4" style="background:var(--fuku-green);border-color:var(--fuku-green);border-radius:50px;">
      相談窓口予約（初回無料）
    </a>
  </div>

</div>

<footer>
  <p class="mb-0">ABC○○法律事務所相談窓口 &nbsp;|&nbsp; AIアシスタント（一般情報提供）</p>
</footer>

<script>
let history = [];

const chatBox  = document.getElementById('chat-box');
const qInput   = document.getElementById('q-input');
const sendBtn  = document.getElementById('send-btn');
const charCount = document.getElementById('char-count');

// 文字数カウント
qInput.addEventListener('input', () => {
  const len = qInput.value.length;
  charCount.textContent = len + ' / 1000文字';
  charCount.style.color = len > 900 ? '#c62828' : '#6c757d';
});

// Enterで送信（Shift+Enterで改行）
qInput.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendQuestion();
  }
});

function addMessage(role, text, isError = false) {
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ' + (isError ? 'error' : role);

  const avatar = document.createElement('div');
  avatar.className = 'avatar';
  avatar.textContent = role === 'user' ? '私' : 'AI';

  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  bubble.textContent = text;

  wrapper.appendChild(avatar);
  wrapper.appendChild(bubble);
  chatBox.appendChild(wrapper);
  chatBox.scrollTop = chatBox.scrollHeight;
  return wrapper;
}

function addTyping() {
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ai';
  wrapper.id = 'typing-indicator';

  const avatar = document.createElement('div');
  avatar.className = 'avatar';
  avatar.textContent = 'AI';

  const bubble = document.createElement('div');
  bubble.className = 'bubble typing-dots';
  bubble.innerHTML = '<span></span><span></span><span></span>';

  wrapper.appendChild(avatar);
  wrapper.appendChild(bubble);
  chatBox.appendChild(wrapper);
  chatBox.scrollTop = chatBox.scrollHeight;
}

function removeTyping() {
  const el = document.getElementById('typing-indicator');
  if (el) el.remove();
}

async function sendQuestion() {
  const question = qInput.value.trim();
  if (!question || sendBtn.disabled) return;
  if (question.length > 1000) {
    alert('質問は1000文字以内で入力してください。');
    return;
  }

  addMessage('user', question);
  history.push({ role: 'user', content: question });
  qInput.value = '';
  charCount.textContent = '0 / 1000文字';
  sendBtn.disabled = true;
  addTyping();

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question, history: history.slice(0, -1) }),
    });
    const data = await res.json();
    removeTyping();

    if (data.error) {
      addMessage('ai', data.error, true);
    } else {
      addMessage('ai', data.answer);
      history.push({ role: 'assistant', content: data.answer });
    }
  } catch (e) {
    removeTyping();
    addMessage('ai', '通信エラーが発生しました。ページを再読み込みして再試行してください。', true);
  }

  sendBtn.disabled = false;
  qInput.focus();
}

function clearChat() {
  if (!confirm('会話履歴をリセットしますか？')) return;
  history = [];
  chatBox.innerHTML = '';
  addMessage('ai', 'こんにちは！ABC○○法律事務所相談窓口のAIアシスタントです。\n相続・離婚・契約・労働問題など、法律に関するご質問をどうぞ。\n専門家への相談前の「整理」のお手伝いをします。');
}
</script>
</body>
</html>
