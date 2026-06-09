const GAS_URL = "https://script.google.com/macros/s/AKfycbxBsf7fJ-MWirzo0Rhwx64ZYtuGG74KC4Ow1eZNwAUin-HI-llhdSnYuncPB5HgghUcSA/exec";
const TOKEN   = "terakoya_2026_01_20_Afky5906_9xP3mQ7vK1zR8dT4uB";

const timeSlots = [
  "10:00〜10:45",
  "11:00〜11:45",
  "13:00〜13:45",
  "14:00〜14:45",
  "15:00〜15:45",
  "16:00〜16:45",
  "17:00〜17:45",
  "18:00〜18:45",
  "19:00〜19:45",
  "20:00〜20:45"
];

const timeSelect = document.getElementById("timeSlot");
const typeSelect = document.getElementById("courseType");

function updateTimeSlots() {
  if (!selectedDate || !typeSelect.value) return;
  timeSelect.innerHTML = "";
  timeSlots.forEach(t => {
    const o = document.createElement("option");
    o.value = t;
    o.textContent = t;
    timeSelect.appendChild(o);
  });
  timeSelect.disabled = false;
}

typeSelect.onchange = updateTimeSlots;
document.addEventListener("dateSelected", updateTimeSlots);

document.getElementById("reservationForm").onsubmit = async e => {
  e.preventDefault();

  // ★ getElementById で明示的に取得（window.name との衝突を回避）
  const nameVal    = document.getElementById("name").value;
  const emailVal   = document.getElementById("email").value;
  const messageVal = document.getElementById("message").value;

  const payload = {
    token:      TOKEN,
    courseType: typeSelect.value,
    date:       selectedDate,
    time:       timeSelect.value,
    name:       nameVal,
    email:      emailVal,
    message:    messageVal,
    userAgent:  navigator.userAgent,
    source:     "website"
  };

  try {
    const res  = await fetch(GAS_URL, {
      method: "POST",
      headers: { "Content-Type": "text/plain" },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    alert(json.ok ? "予約を受け付けました" : "送信失敗：" + (json.message || json.msg || "不明なエラー"));
  } catch (err) {
    alert("通信エラーが発生しました。もう一度お試しください。\n" + err.message);
  }
};
