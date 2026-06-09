import { useState, useMemo } from "react";
import { format, addDays, subDays, startOfDay, eachDayOfInterval } from "date-fns";
import { ja } from "date-fns/locale";
import { 
  useItems, 
  useCreateItem, 
  useUpdateItem, 
  useDeleteItem, 
  useEntries, 
  useUpdateEntry,
  useBulkClearEntries
} from "@/hooks/use-checklist";
import { StatusCell } from "@/components/StatusCell";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogInput,
  DialogClose
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { 
  ChevronLeft, 
  ChevronRight, 
  Trash2, 
  Check, 
  Save, 
  CheckCircle2, 
  Edit2, 
  RotateCcw, 
  Plus, 
  MinusCircle,
  Loader2
} from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { cn } from "@/lib/utils";

// Helper to format date for API (YYYY-MM-DD)
const formatDateApi = (date: Date) => format(date, "yyyy-MM-dd");
// Helper to format date for Display (MM/DD)
const formatDateDisplay = (date: Date) => format(date, "MM/dd");

export default function Checklist() {
  const { toast } = useToast();
  
  // -- State --
  const [currentDate, setCurrentDate] = useState(startOfDay(new Date()));
  const [displayDays, setDisplayDays] = useState(7);
  const [selectedItemIds, setSelectedItemIds] = useState<Set<number>>(new Set());
  const [rowEditNames, setRowEditNames] = useState<Record<number, string>>({});
  
  // Dialog States
  const [isRenameDialogOpen, setIsRenameDialogOpen] = useState(false);
  const [isInsertDialogOpen, setIsInsertDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<{id: number, name: string} | null>(null);
  const [newItemName, setNewItemName] = useState("");

  // -- Queries --
  const { data: items = [], isLoading: isLoadingItems } = useItems();
  
  // Calculate date range
  const startDate = currentDate;
  const endDate = addDays(currentDate, displayDays - 1);
  const dateRange = useMemo(() => {
    return eachDayOfInterval({ start: startDate, end: endDate });
  }, [startDate, endDate]);

  const { data: entries = [], isLoading: isLoadingEntries } = useEntries(
    formatDateApi(startDate), 
    formatDateApi(endDate)
  );

  // -- Mutations --
  const createItemMutation = useCreateItem();
  const updateItemMutation = useUpdateItem();
  const deleteItemMutation = useDeleteItem();
  const updateEntryMutation = useUpdateEntry();
  const bulkClearMutation = useBulkClearEntries();

  // -- Handlers --

  const handlePrevDate = () => setCurrentDate(prev => subDays(prev, 1));
  const handleNextDate = () => setCurrentDate(prev => addDays(prev, 1));
  const handleToday = () => setCurrentDate(startOfDay(new Date()));

  const toggleItemSelection = (id: number) => {
    const newSet = new Set(selectedItemIds);
    if (newSet.has(id)) newSet.delete(id);
    else newSet.add(id);
    setSelectedItemIds(newSet);
  };

  const getEntryValue = (itemId: number, dateStr: string) => {
    const entry = entries.find(e => e.itemId === itemId && e.date === dateStr);
    return entry ? entry.status : "ー";
  };

  const handleStatusChange = (itemId: number, dateStr: string, newStatus: string) => {
    updateEntryMutation.mutate({
      itemId,
      date: dateStr,
      status: newStatus
    });
  };

  const handleDeleteChecks = () => {
    if (!confirm("表示されている期間のチェックを全て削除しますか？")) return;
    bulkClearMutation.mutate({
      startDate: formatDateApi(startDate),
      endDate: formatDateApi(endDate)
    }, {
      onSuccess: () => toast({ title: "削除しました", description: "チェック項目をクリアしました" })
    });
  };

  const handleCheck = () => {
    // Logic: Mark all selected items as "checked" (e.g., "◎") for TODAY?
    // Or perhaps the leftmost visible day? Assuming 'Today' logic for simplicity or first visible day.
    // Let's implement: Set '◎' for selected items on the *current focused date* (first visible column)
    if (selectedItemIds.size === 0) {
      toast({ title: "エラー", description: "物品を選択してください", variant: "destructive" });
      return;
    }
    
    const targetDate = formatDateApi(startDate);
    let count = 0;
    selectedItemIds.forEach(id => {
      updateEntryMutation.mutate({ itemId: id, date: targetDate, status: "◎" });
      count++;
    });
    toast({ title: "完了", description: `${count}件のアイテムをチェックしました` });
  };

  const handleSave = () => {
    // In a real app with local state this would push changes. 
    // Since we save on change, this is just a feedback trigger.
    toast({ title: "保存しました", description: "データは最新です", className: "bg-green-600 text-white border-none" });
  };

  const handleConfirmItem = () => {
    if (selectedItemIds.size === 0) return;
    const targetDate = formatDateApi(startDate);
    selectedItemIds.forEach(id => {
      updateEntryMutation.mutate({ itemId: id, date: targetDate, status: "〇" }); // Using Circle for 'confirmed'
    });
    toast({ title: "確定しました", description: "選択項目を確定済みにしました" });
  };

  const handleRenameClick = () => {
    if (selectedItemIds.size !== 1) {
      toast({ title: "注意", description: "変更する物品を1つだけ選択してください", variant: "destructive" });
      return;
    }
    const id = Array.from(selectedItemIds)[0];
    const item = items.find(i => i.id === id);
    if (item) {
      setEditingItem({ id: item.id, name: item.name });
      setIsRenameDialogOpen(true);
    }
  };

  const submitRename = () => {
    if (!editingItem) return;
    updateItemMutation.mutate({ id: editingItem.id, name: editingItem.name }, {
      onSuccess: () => {
        setIsRenameDialogOpen(false);
        setEditingItem(null);
        toast({ title: "変更しました", description: "物品名を更新しました" });
      }
    });
  };

  const handleReset = () => {
    if (!confirm("全ての選択を解除し、表示をリセットしますか？")) return;
    setSelectedItemIds(new Set());
    handleToday();
  };

  const handleInsertRow = () => {
    setNewItemName("");
    setIsInsertDialogOpen(true);
  };

  const submitInsertItem = () => {
    if (!newItemName.trim()) return;
    createItemMutation.mutate({ name: newItemName, order: items.length + 1 }, {
      onSuccess: () => {
        setIsInsertDialogOpen(false);
        setNewItemName("");
        toast({ title: "追加しました", description: "新しい物品を追加しました" });
      }
    });
  };

  const handleDeleteRow = () => {
    if (selectedItemIds.size === 0) {
      toast({ title: "エラー", description: "削除する物品を選択してください", variant: "destructive" });
      return;
    }
    if (!confirm(`選択した${selectedItemIds.size}件の物品を削除しますか？`)) return;

    selectedItemIds.forEach(id => {
      deleteItemMutation.mutate(id);
    });
    setSelectedItemIds(new Set());
    toast({ title: "削除しました", description: "物品を削除しました" });
  };

  // --- Render ---

  if (isLoadingItems) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-slate-50">
        <div className="flex flex-col items-center gap-4">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
          <p className="text-muted-foreground font-medium">Loading checklist...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background flex flex-col font-sans">
      {/* 1. Header */}
      <header className="bg-primary text-primary-foreground py-4 px-6 shadow-md">
        <h1 className="text-2xl font-bold tracking-tight">持ち物リスト (Checklist)</h1>
      </header>

      {/* 2. Control Bar */}
      <div className="border-b bg-card/50 backdrop-blur supports-[backdrop-filter]:bg-background/60 sticky top-0 z-10 p-4 space-y-4">
        
        {/* Top Row: Navigation & Display Options */}
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2 bg-muted p-1 rounded-lg border">
            <Button variant="ghost" size="sm" onClick={handlePrevDate} className="h-8 px-2 hover:bg-background">
              <ChevronLeft className="h-4 w-4 mr-1" />
              前へ
            </Button>
            <Button variant="outline" size="sm" onClick={handleToday} className="h-8 bg-background shadow-sm">
              今日 ({format(new Date(), "MM/dd")})
            </Button>
            <Button variant="ghost" size="sm" onClick={handleNextDate} className="h-8 px-2 hover:bg-background">
              次へ
              <ChevronRight className="h-4 w-4 ml-1" />
            </Button>
          </div>

          <div className="flex items-center gap-2">
            <Label htmlFor="days-select" className="text-sm font-medium text-muted-foreground">表示期間:</Label>
            <Select value={displayDays.toString()} onValueChange={(v) => setDisplayDays(parseInt(v))}>
              <SelectTrigger id="days-select" className="w-[120px] h-9">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="3">3日間</SelectItem>
                <SelectItem value="7">7日間</SelectItem>
                <SelectItem value="14">14日間</SelectItem>
                <SelectItem value="30">30日間</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Bottom Row: Action Buttons */}
        <div className="flex flex-wrap items-center gap-2">
          <Button 
            className="btn-action-red h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95"
            onClick={handleDeleteChecks}
          >
            <Trash2 className="h-4 w-4 mr-1.5" />
            チェック項目全て削除
          </Button>
          
          <div className="w-px h-8 bg-border mx-1 hidden md:block" />

          <Button className="btn-action-blue h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleCheck}>
            <Check className="h-4 w-4 mr-1.5" />
            チェックする
          </Button>
          <Button className="btn-action-green h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleSave}>
            <Save className="h-4 w-4 mr-1.5" />
            保存する
          </Button>
          <Button className="btn-action-purple h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleConfirmItem}>
            <CheckCircle2 className="h-4 w-4 mr-1.5" />
            項目確定
          </Button>
          <Button className="btn-action-yellow h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleRenameClick}>
            <Edit2 className="h-4 w-4 mr-1.5" />
            物品名変更
          </Button>
          <Button className="btn-action-gray h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleReset}>
            <RotateCcw className="h-4 w-4 mr-1.5" />
            リセット
          </Button>

          <div className="w-px h-8 bg-border mx-1 hidden md:block" />

          <Button className="btn-action-orange h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleInsertRow}>
            <Plus className="h-4 w-4 mr-1.5" />
            行の挿入
          </Button>
          <Button className="btn-action-red h-9 px-3 text-xs md:text-sm font-medium transition-all hover:scale-105 active:scale-95" onClick={handleDeleteRow}>
            <MinusCircle className="h-4 w-4 mr-1.5" />
            行の削除
          </Button>
        </div>
      </div>

      {/* 3. Main Data Grid */}
      <div className="flex-1 overflow-auto p-4 md:p-6 bg-slate-50/50">
        <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
          <div className="overflow-x-auto">
            <table className="checklist-grid w-full">
              <thead>
                <tr>
                  <th className="sticky left-0 z-20 bg-muted/95 min-w-[200px] border-r shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                    物品 (Items)
                  </th>
                  {dateRange.map((date) => (
                    <th key={date.toISOString()} className="min-w-[60px] font-mono text-xs md:text-sm">
                      <div className="flex flex-col items-center">
                        <span className="text-[10px] text-muted-foreground uppercase">{format(date, "EEE", { locale: ja })}</span>
                        <span>{formatDateDisplay(date)}</span>
                      </div>
                    </th>
                  ))}
                  <th className="w-[120px] text-center">操作</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={dateRange.length + 2} className="h-32 text-center text-muted-foreground bg-slate-50">
                      アイテムがありません。"行の挿入"ボタンで追加してください。
                    </td>
                  </tr>
                ) : (
                  items.map((item) => (
                    <tr key={item.id} className="group hover:bg-sky-200 transition-colors">
                      <td className="sticky left-0 z-10 bg-white group-hover:bg-sky-200 border-r shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                        <div className="flex items-center gap-3 px-2">
                          <Checkbox 
                            checked={selectedItemIds.has(item.id)}
                            onCheckedChange={() => toggleItemSelection(item.id)}
                            className="translate-y-[1px]"
                          />
                          <Input
                            value={rowEditNames[item.id] !== undefined ? rowEditNames[item.id] : item.name}
                            onChange={(e) => setRowEditNames(prev => ({ ...prev, [item.id]: e.target.value }))}
                            onClick={() => setSelectedItemIds(new Set([item.id]))}
                            className="h-7 text-sm font-medium border-0 shadow-none focus-visible:ring-1 px-1"
                          />
                        </div>
                      </td>
                      {dateRange.map((date) => {
                        const dateStr = formatDateApi(date);
                        const value = getEntryValue(item.id, dateStr);
                        return (
                          <td key={dateStr} className="p-0 h-10">
                            <StatusCell
                              value={value}
                              onChange={(newStatus) => handleStatusChange(item.id, dateStr, newStatus)}
                              isLoading={updateEntryMutation.isPending}
                            />
                          </td>
                        );
                      })}
                      <td className="text-center">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-muted-foreground hover:text-green-600 hover:bg-green-50"
                          onClick={() => {
                            const name = rowEditNames[item.id] !== undefined ? rowEditNames[item.id] : item.name;
                            updateItemMutation.mutate({ id: item.id, name }, {
                              onSuccess: () => {
                                setRowEditNames(prev => { const n = { ...prev }; delete n[item.id]; return n; });
                                toast({ title: "保存しました", description: `"${name}" を保存しました` });
                              }
                            });
                          }}
                          disabled={updateItemMutation.isPending}
                        >
                          <Save className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                          onClick={() => {
                            if (confirm(`"${item.name}" を削除しますか？`)) {
                              deleteItemMutation.mutate(item.id);
                            }
                          }}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* --- Dialogs --- */}
      
      {/* Insert Dialog */}
      <Dialog open={isInsertDialogOpen} onOpenChange={setIsInsertDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>新しい物品を追加</DialogTitle>
          </DialogHeader>
          <div className="py-4">
            <Label htmlFor="itemName" className="mb-2 block">物品名</Label>
            <Input 
              id="itemName"
              value={newItemName}
              onChange={(e) => setNewItemName(e.target.value)}
              placeholder="例: ノートPC"
              autoFocus
              onKeyDown={(e) => e.key === 'Enter' && submitInsertItem()}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsInsertDialogOpen(false)}>キャンセル</Button>
            <Button onClick={submitInsertItem} disabled={!newItemName.trim() || createItemMutation.isPending}>
              {createItemMutation.isPending ? "追加中..." : "追加"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Rename Dialog */}
      <Dialog open={isRenameDialogOpen} onOpenChange={setIsRenameDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>物品名を変更</DialogTitle>
          </DialogHeader>
          <div className="py-4">
            <Label htmlFor="renameItem" className="mb-2 block">新しい名前</Label>
            <Input 
              id="renameItem"
              value={editingItem?.name || ""}
              onChange={(e) => setEditingItem(prev => prev ? { ...prev, name: e.target.value } : null)}
              autoFocus
              onKeyDown={(e) => e.key === 'Enter' && submitRename()}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsRenameDialogOpen(false)}>キャンセル</Button>
            <Button onClick={submitRename} disabled={!editingItem?.name.trim() || updateItemMutation.isPending}>
              {updateItemMutation.isPending ? "保存中..." : "保存"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  );
}
