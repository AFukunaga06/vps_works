// State
let allItems = [];
let allEntries = [];
let currentStartDate = new Date();
let displayDays = 2;

const STATUS_OPTIONS = ['ー', '◎', '〇', '△'];

// Helper: Format date as YYYY-MM-DD
function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Helper: Format date for display (M/D)
function formatDateDisplay(date) {
  const month = date.getMonth() + 1;
  const day = date.getDate();
  return `${month}/${day}`;
}

// API Calls
async function fetchItems() {
  try {
    const response = await fetch('/api/items');
    allItems = await response.json();
    return allItems;
  } catch (error) {
    console.error('Error fetching items:', error);
    return [];
  }
}

async function fetchEntries(startDate, endDate) {
  try {
    const params = new URLSearchParams({
      startDate: formatDate(startDate),
      endDate: formatDate(endDate)
    });
    const response = await fetch(`/api/entries?${params}`);
    allEntries = await response.json();
    return allEntries;
  } catch (error) {
    console.error('Error fetching entries:', error);
    return [];
  }
}

async function addItem(name) {
  try {
    const response = await fetch('/api/items', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, order: 0 })
    });
    const newItem = await response.json();
    allItems.push(newItem);
    return newItem;
  } catch (error) {
    console.error('Error adding item:', error);
  }
}

async function deleteItem(id) {
  try {
    await fetch(`/api/items/${id}`, { method: 'DELETE' });
    allItems = allItems.filter(item => item.id !== id);
  } catch (error) {
    console.error('Error deleting item:', error);
  }
}

async function updateItem(id, name) {
  try {
    const response = await fetch(`/api/items/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name })
    });
    const updated = await response.json();
    const idx = allItems.findIndex(i => i.id === id);
    if (idx >= 0) allItems[idx] = updated;
    return updated;
  } catch (error) {
    console.error('Error updating item:', error);
  }
}

async function updateEntry(itemId, date, status) {
  try {
    const response = await fetch('/api/entries', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ itemId, date: formatDate(date), status })
    });
    const updatedEntry = await response.json();
    const existingIdx = allEntries.findIndex(e => e.itemId === itemId && e.date === formatDate(date));
    if (existingIdx >= 0) {
      allEntries[existingIdx] = updatedEntry;
    } else {
      allEntries.push(updatedEntry);
    }
    return updatedEntry;
  } catch (error) {
    console.error('Error updating entry:', error);
  }
}

async function clearEntries(startDate, endDate) {
  try {
    await fetch('/api/entries/clear', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ startDate: formatDate(startDate), endDate: formatDate(endDate) })
    });
    await fetchEntries(startDate, endDate);
  } catch (error) {
    console.error('Error clearing entries:', error);
  }
}

function getEntryStatus(itemId, date) {
  const entry = allEntries.find(e => e.itemId === itemId && e.date === formatDate(date));
  return entry ? entry.status : 'ー';
}

// Build date array for current view
function getDates() {
  const dates = [];
  for (let i = 0; i < displayDays; i++) {
    const date = new Date(currentStartDate);
    date.setDate(date.getDate() + i);
    dates.push(date);
  }
  return dates;
}

// Render date headers
function renderDateHeaders() {
  const headerRow = document.getElementById('headerRow');
  headerRow.innerHTML = '';

  // Item column header
  const thItem = document.createElement('th');
  thItem.className = 'col-item';
  thItem.textContent = '物品';
  headerRow.appendChild(thItem);

  // Date column headers
  getDates().forEach(date => {
    const th = document.createElement('th');
    th.className = 'col-date';
    th.textContent = formatDateDisplay(date);
    headerRow.appendChild(th);
  });

  // Operation column header
  const thOp = document.createElement('th');
  thOp.className = 'col-operation';
  thOp.textContent = '操作';
  headerRow.appendChild(thOp);
}

// Render table body
function renderTable() {
  const tableBody = document.getElementById('tableBody');
  tableBody.innerHTML = '';

  const dates = getDates();

  allItems.forEach(item => {
    const row = document.createElement('tr');

    // Item name cell
    const nameCell = document.createElement('td');
    nameCell.className = 'col-item';
    nameCell.innerHTML = `
      <div class="item-name">
        <input type="checkbox" class="item-checkbox" data-item-id="${item.id}" />
        <input type="text" class="item-name-input" data-item-id="${item.id}" value="${item.name.replace(/"/g, '&quot;')}" />
      </div>
    `;
    row.appendChild(nameCell);

    // Date cells with dropdown
    dates.forEach(date => {
      const dateCell = document.createElement('td');
      dateCell.className = 'col-date';

      const status = getEntryStatus(item.id, date);
      const select = document.createElement('select');
      select.className = 'status-select';
      select.dataset.itemId = item.id;
      select.dataset.date = formatDate(date);

      STATUS_OPTIONS.forEach(option => {
        const optionEl = document.createElement('option');
        optionEl.value = option;
        optionEl.textContent = option;
        optionEl.selected = option === status;
        select.appendChild(optionEl);
      });

      select.addEventListener('change', async (e) => {
        await updateEntry(item.id, date, e.target.value);
      });

      dateCell.appendChild(select);
      row.appendChild(dateCell);
    });

    // Operation cell
    const opCell = document.createElement('td');
    opCell.className = 'col-operation';

    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn-save';
    saveBtn.textContent = '保存';
    saveBtn.addEventListener('click', async () => {
      const input = row.querySelector('.item-name-input');
      const newName = input.value.trim();
      if (!newName) { alert('物品名を入力してください'); return; }
      await updateItem(item.id, newName);
      rowSaveMsg.style.display = '';
      clearTimeout(rowSaveMsg._hideTimer);
      rowSaveMsg._hideTimer = setTimeout(() => { rowSaveMsg.style.display = 'none'; }, 3000);
    });
    opCell.appendChild(saveBtn);

    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'btn-delete';
    deleteBtn.textContent = '削除';
    deleteBtn.addEventListener('click', async () => {
      if (confirm('この項目を削除してもよろしいですか？')) {
        await deleteItem(item.id);
        await render();
      }
    });
    opCell.appendChild(deleteBtn);
    const rowSaveMsg = document.createElement('span');
    rowSaveMsg.textContent = '保存しました';
    rowSaveMsg.style.cssText = 'display: none; margin-left: 8px; color: #16a34a; font-weight: 600; white-space: nowrap;';
    opCell.appendChild(rowSaveMsg);
    row.appendChild(opCell);

    tableBody.appendChild(row);
  });
}

// Main render
async function render() {
  renderDateHeaders();
  renderTable();
}

// Event Handlers
function setupEventListeners() {
  document.getElementById('addItemBtn').addEventListener('click', async () => {
    const input = document.getElementById('newItemInput');
    if (input.value.trim()) {
      await addItem(input.value.trim());
      input.value = '';
      await render();
    }
  });

  document.getElementById('newItemInput').addEventListener('keypress', async (e) => {
    if (e.key === 'Enter') document.getElementById('addItemBtn').click();
  });

  document.getElementById('prevBtn').addEventListener('click', async () => {
    currentStartDate.setDate(currentStartDate.getDate() - displayDays);
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + displayDays - 1);
    await fetchEntries(currentStartDate, endDate);
    await render();
  });

  document.getElementById('todayBtn').addEventListener('click', async () => {
    currentStartDate = new Date();
    currentStartDate.setHours(0, 0, 0, 0);
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + displayDays - 1);
    await fetchEntries(currentStartDate, endDate);
    await render();
  });

  document.getElementById('nextBtn').addEventListener('click', async () => {
    currentStartDate.setDate(currentStartDate.getDate() + displayDays);
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + displayDays - 1);
    await fetchEntries(currentStartDate, endDate);
    await render();
  });

  document.getElementById('daysSelect').addEventListener('change', async (e) => {
    displayDays = parseInt(e.target.value);
    currentStartDate = new Date();
    currentStartDate.setHours(0, 0, 0, 0);
    const endDate = new Date(currentStartDate);
    endDate.setDate(endDate.getDate() + displayDays - 1);
    await fetchEntries(currentStartDate, endDate);
    await render();
  });

  document.getElementById('clearChecksBtn').addEventListener('click', async () => {
    if (confirm('すべてのチェック項目を削除してもよろしいですか？')) {
      const endDate = new Date(currentStartDate);
      endDate.setDate(endDate.getDate() + displayDays - 1);
      await clearEntries(currentStartDate, endDate);
      await render();
    }
  });

  document.getElementById('checkAllBtn').addEventListener('click', async () => {
    const dates = getDates();
    for (const item of allItems) {
      for (const date of dates) {
        await updateEntry(item.id, date, '◎');
      }
    }
    await render();
  });

  document.getElementById('saveBtn').addEventListener('click', () => {
    const msg = document.getElementById('saveMessage');
    msg.style.display = '';
    clearTimeout(msg._hideTimer);
    msg._hideTimer = setTimeout(() => { msg.style.display = 'none'; }, 3000);
  });

  document.getElementById('confirmItemBtn').addEventListener('click', () => {
    alert('項目を確定しました！');
  });

  document.getElementById('renameItemBtn').addEventListener('click', async () => {
    const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
    if (selectedCheckboxes.length === 0) { alert('物品を選択してください'); return; }
    if (selectedCheckboxes.length > 1) { alert('1つだけ選択してください'); return; }
    const itemId = parseInt(selectedCheckboxes[0].dataset.itemId);
    const item = allItems.find(i => i.id === itemId);
    const newName = prompt('新しい物品名を入力してください:', item.name);
    if (newName && newName.trim()) {
      await updateItem(itemId, newName.trim());
      await render();
    }
  });

  document.getElementById('resetBtn').addEventListener('click', async () => {
    if (confirm('すべてを初期化してもよろしいですか？')) {
      const endDate = new Date(currentStartDate);
      endDate.setDate(endDate.getDate() + displayDays - 1);
      await clearEntries(currentStartDate, endDate);
      await render();
    }
  });

  document.getElementById('insertRowBtn').addEventListener('click', async () => {
    const name = prompt('新しい物品名を入力してください:');
    if (name && name.trim()) {
      await addItem(name.trim());
      await render();
    }
  });

  document.getElementById('deleteRowBtn').addEventListener('click', async () => {
    const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
    if (selectedCheckboxes.length === 0) { alert('削除する物品を選択してください'); return; }
    if (confirm('選択した物品を削除してもよろしいですか？')) {
      for (const checkbox of selectedCheckboxes) {
        await deleteItem(parseInt(checkbox.dataset.itemId));
      }
      await render();
    }
  });
}

// Initialize app
async function init() {
  currentStartDate = new Date();
  currentStartDate.setHours(0, 0, 0, 0);

  const daysSelect = document.getElementById('daysSelect');
  if (daysSelect) daysSelect.value = String(displayDays);

  await fetchItems();
  const endDate = new Date(currentStartDate);
  endDate.setDate(endDate.getDate() + displayDays - 1);
  await fetchEntries(currentStartDate, endDate);

  setupEventListeners();
  await render();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
