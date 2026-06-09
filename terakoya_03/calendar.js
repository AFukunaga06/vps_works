let current = new Date();
let selectedDate = null;

const title = document.getElementById("calendarTitle");
const grid  = document.getElementById("calendarGrid");

function renderCalendar() {
  grid.querySelectorAll(".day").forEach(d => d.remove());

  const y = current.getFullYear();
  const m = current.getMonth();
  title.textContent = `${y}年 ${m + 1}月`;

  const first = new Date(y, m, 1).getDay();
  const last  = new Date(y, m + 1, 0).getDate();

  for (let i = 0; i < first; i++) {
    grid.appendChild(document.createElement("div"));
  }

  for (let d = 1; d <= last; d++) {
    const el = document.createElement("div");
    el.textContent = d;
    const today = new Date();
    el.className = "day";
    if (y === today.getFullYear() && m === today.getMonth() && d === today.getDate()) el.classList.add("today");
    el.onclick = () => {
      document.querySelectorAll(".day").forEach(x => x.classList.remove("selected"));
      el.classList.add("selected");
      selectedDate = `${y}/${m+1}/${d}`;
      document.getElementById("selectedDate").textContent = `選択日：${selectedDate}`;
      document.dispatchEvent(new Event("dateSelected"));
    };
    grid.appendChild(el);
  }
}

document.getElementById("prevMonth").onclick = () => {
  current.setMonth(current.getMonth() - 1);
  renderCalendar();
};
document.getElementById("nextMonth").onclick = () => {
  current.setMonth(current.getMonth() + 1);
  renderCalendar();
};

renderCalendar();
