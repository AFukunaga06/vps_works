import tkinter as tk
from tkinter import messagebox
from tkcalendar import DateEntry
import subprocess
import threading
import datetime
import os

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

def run_scraper():
    try:
        date_from = cal_from.get_date().strftime('%Y-%m-%d')
        date_to   = cal_to.get_date().strftime('%Y-%m-%d')
    except Exception:
        messagebox.showerror('エラー', '日付が正しくありません。')
        return

    if cal_from.get_date() > cal_to.get_date():
        messagebox.showerror('エラー', '開始日は終了日より前にしてください。')
        return

    btn_run.config(state='disabled')
    label_status.config(text='実行中...（Chromeが自動で開きます）', fg='blue')

    def task():
        try:
            result = subprocess.run(
                ['node', 'amazon_scrape.js', date_from, date_to],
                cwd=SCRIPT_DIR,
                capture_output=True,
                text=True,
                encoding='utf-8'
            )
            output = result.stdout + result.stderr
            text_log.config(state='normal')
            text_log.delete('1.0', tk.END)
            text_log.insert(tk.END, output)
            text_log.config(state='disabled')
            if result.returncode == 0:
                label_status.config(text='完了！Excelを保存しました。', fg='green')
            else:
                label_status.config(text='エラーが発生しました。', fg='red')
        except Exception as e:
            label_status.config(text=f'エラー: {e}', fg='red')
        finally:
            btn_run.config(state='normal')

    threading.Thread(target=task, daemon=True).start()

# ウィンドウ
root = tk.Tk()
root.title('Amazon 注文履歴 Excel出力')
root.resizable(False, False)

today = datetime.date.today()
first_of_year = datetime.date(today.year, 1, 1)

pad = {'padx': 10, 'pady': 6}

tk.Label(root, text='Amazon注文履歴 Excel出力ツール', font=('', 13, 'bold')).grid(
    row=0, column=0, columnspan=3, pady=(15, 10))

# 開始日
tk.Label(root, text='開始日：', font=('', 10)).grid(row=1, column=0, sticky='e', **pad)
cal_from = DateEntry(root, width=14, font=('', 10),
                     date_pattern='yyyy/mm/dd',
                     year=first_of_year.year,
                     month=first_of_year.month,
                     day=first_of_year.day,
                     locale='ja_JP')
cal_from.grid(row=1, column=1, columnspan=2, sticky='w', **pad)

# 終了日
tk.Label(root, text='終了日：', font=('', 10)).grid(row=2, column=0, sticky='e', **pad)
cal_to = DateEntry(root, width=14, font=('', 10),
                   date_pattern='yyyy/mm/dd',
                   year=today.year,
                   month=today.month,
                   day=today.day,
                   locale='ja_JP')
cal_to.grid(row=2, column=1, columnspan=2, sticky='w', **pad)

# 実行ボタン
btn_run = tk.Button(root, text='  Excel出力を実行  ', font=('', 11, 'bold'),
                    bg='#FF9900', fg='white', command=run_scraper)
btn_run.grid(row=3, column=0, columnspan=3, pady=12)

# ステータス
label_status = tk.Label(root, text='期間を指定して「Excel出力を実行」を押してください。', fg='gray')
label_status.grid(row=4, column=0, columnspan=3)

# ログ表示
text_log = tk.Text(root, width=60, height=10, state='disabled', font=('Courier', 9))
text_log.grid(row=5, column=0, columnspan=3, padx=10, pady=(5, 15))

root.mainloop()
