import { useState, useEffect } from "react";
import { format } from "date-fns";
import { ja } from "date-fns/locale";
import { z } from "zod";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { Clock, User, Mail, Phone, FileText, CheckCircle2, ArrowLeft } from "lucide-react";
import { useGetAvailability, useCreateBooking } from "@workspace/api-client-react";

interface BookingModalProps {
  selectedDate: Date | null;
  onClose: () => void;
}

const formSchema = z.object({
  name: z.string().min(1, "お名前を入力してください"),
  email: z.string().email("正しいメールアドレスを入力してください"),
  phone: z.string().optional(),
  notes: z.string().optional(),
});

type FormValues = z.infer<typeof formSchema>;

export function BookingModal({ selectedDate, onClose }: BookingModalProps) {
  const { toast } = useToast();
  const [selectedTime, setSelectedTime] = useState<string | null>(null);
  const [step, setStep] = useState<"time" | "form" | "success">("time");

  const formattedDate = selectedDate ? format(selectedDate, "yyyy-MM-dd") : "";
  
  const { data: availability, isLoading: isLoadingTimes } = useGetAvailability(
    { date: formattedDate },
    { query: { enabled: !!selectedDate && step === "time" } }
  );

  const { mutate: createBooking, isPending } = useCreateBooking({
    mutation: {
      onSuccess: () => {
        setStep("success");
      },
      onError: (error) => {
        toast({
          title: "予約エラー",
          description: error?.error || "予約の作成に失敗しました。もう一度お試しください。",
          variant: "destructive",
        });
      },
    }
  });

  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      notes: "",
    },
  });

  // Reset state when a new date is selected
  useEffect(() => {
    if (selectedDate) {
      setStep("time");
      setSelectedTime(null);
      form.reset();
    }
  }, [selectedDate, form]);

  if (!selectedDate) return null;

  const dateLabel = format(selectedDate, "yyyy年M月d日 (E)", { locale: ja });

  const onSubmit = (data: FormValues) => {
    if (!selectedTime) return;
    createBooking({
      data: {
        date: formattedDate,
        time: selectedTime,
        name: data.name,
        email: data.email,
        phone: data.phone,
        notes: data.notes,
      },
    });
  };

  return (
    <Dialog open={!!selectedDate} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-[500px] p-0 overflow-hidden bg-background rounded-2xl shadow-2xl border-border/50">
        <DialogHeader className="p-6 pb-4 bg-primary/5 border-b border-primary/10">
          <DialogTitle className="text-2xl text-foreground font-display flex items-center gap-2">
            {step === "form" && (
              <button 
                onClick={() => setStep("time")}
                className="p-1 hover:bg-primary/10 rounded-full transition-colors mr-1"
                aria-label="戻る"
              >
                <ArrowLeft className="w-5 h-5 text-primary" />
              </button>
            )}
            {dateLabel}
          </DialogTitle>
          <DialogDescription className="text-muted-foreground text-sm mt-1">
            法律相談（相談前整理サポート）の予約
          </DialogDescription>
        </DialogHeader>

        <div className="p-6">
          {step === "time" && (
            <div className="animate-in fade-in slide-in-from-bottom-4 duration-300">
              <h3 className="font-medium text-foreground mb-4 flex items-center gap-2">
                <Clock className="w-4 h-4 text-primary" />
                ご希望の時間帯を選択してください
              </h3>
              
              {isLoadingTimes ? (
                <div className="grid grid-cols-2 gap-3">
                  {[1, 2, 3, 4].map((i) => (
                    <div key={i} className="h-14 bg-muted animate-pulse rounded-xl" />
                  ))}
                </div>
              ) : availability?.slots && availability.slots.length > 0 ? (
                <div className="grid grid-cols-2 gap-3">
                  {availability.slots.map((slot) => (
                    <button
                      key={slot.time}
                      disabled={!slot.available || slot.booked}
                      onClick={() => {
                        setSelectedTime(slot.time);
                        setStep("form");
                      }}
                      className={`
                        py-3 px-4 rounded-xl text-center font-medium border-2 transition-all duration-200
                        ${
                          slot.available && !slot.booked
                            ? "bg-background border-primary/20 text-foreground hover:border-primary hover:bg-primary/5 hover:-translate-y-0.5 active:translate-y-0 shadow-sm"
                            : "bg-muted/50 border-muted text-muted-foreground cursor-not-allowed opacity-60"
                        }
                      `}
                    >
                      {slot.time}
                      {slot.booked && <span className="block text-xs font-normal mt-0.5">満席</span>}
                      {!slot.booked && !slot.available && <span className="block text-xs font-normal mt-0.5">-</span>}
                      {slot.available && !slot.booked && <span className="block text-xs font-normal mt-0.5 text-primary">予約可</span>}
                    </button>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  <p>この日の予約枠は設定されていません。</p>
                  <p className="text-sm mt-1">別の日付をお試しください。</p>
                </div>
              )}
            </div>
          )}

          {step === "form" && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <div className="bg-primary/5 text-primary px-4 py-3 rounded-xl mb-6 font-medium flex items-center justify-between border border-primary/10">
                <span>選択した日時:</span>
                <span className="text-lg">{dateLabel} {selectedTime}</span>
              </div>

              <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name" className="text-foreground flex items-center gap-1.5">
                    <User className="w-4 h-4 text-muted-foreground" />
                    お名前 <span className="text-destructive text-xs ml-1">*</span>
                  </Label>
                  <Input 
                    id="name" 
                    placeholder="山田 太郎" 
                    {...form.register("name")}
                    className="focus-visible:ring-primary/20 focus-visible:border-primary border-border bg-background shadow-sm"
                  />
                  {form.formState.errors.name && (
                    <p className="text-sm text-destructive">{form.formState.errors.name.message}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email" className="text-foreground flex items-center gap-1.5">
                    <Mail className="w-4 h-4 text-muted-foreground" />
                    メールアドレス <span className="text-destructive text-xs ml-1">*</span>
                  </Label>
                  <Input 
                    id="email" 
                    type="email" 
                    placeholder="taro@example.com" 
                    {...form.register("email")}
                    className="focus-visible:ring-primary/20 focus-visible:border-primary border-border bg-background shadow-sm"
                  />
                  {form.formState.errors.email && (
                    <p className="text-sm text-destructive">{form.formState.errors.email.message}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="phone" className="text-foreground flex items-center gap-1.5">
                    <Phone className="w-4 h-4 text-muted-foreground" />
                    電話番号 <span className="text-muted-foreground text-xs ml-1 font-normal">(任意)</span>
                  </Label>
                  <Input 
                    id="phone" 
                    type="tel" 
                    placeholder="090-0000-0000" 
                    {...form.register("phone")}
                    className="focus-visible:ring-primary/20 focus-visible:border-primary border-border bg-background shadow-sm"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="notes" className="text-foreground flex items-center gap-1.5">
                    <FileText className="w-4 h-4 text-muted-foreground" />
                    ご相談の概要 <span className="text-muted-foreground text-xs ml-1 font-normal">(任意)</span>
                  </Label>
                  <Textarea 
                    id="notes" 
                    placeholder="現在お困りの状況について、簡単にご記入ください。" 
                    rows={4}
                    {...form.register("notes")}
                    className="resize-none focus-visible:ring-primary/20 focus-visible:border-primary border-border bg-background shadow-sm"
                  />
                </div>

                <div className="pt-4">
                  <Button 
                    type="submit" 
                    disabled={isPending}
                    className="w-full h-12 text-base font-bold bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg shadow-primary/25 transition-all hover:-translate-y-0.5 active:translate-y-0 rounded-xl"
                  >
                    {isPending ? "予約を確定しています..." : "この内容で予約する"}
                  </Button>
                  <p className="text-xs text-center text-muted-foreground mt-3">
                    ※ 予約確定後、ご入力いただいたメールアドレスに確認メールが送信されます。
                  </p>
                </div>
              </form>
            </div>
          )}

          {step === "success" && (
            <div className="text-center py-8 animate-in zoom-in-95 duration-500">
              <div className="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6 text-primary">
                <CheckCircle2 className="w-10 h-10" />
              </div>
              <h3 className="text-2xl font-display font-bold text-foreground mb-2">予約が完了しました</h3>
              <p className="text-muted-foreground mb-8">
                ご予約ありがとうございます。<br />
                確認メールを送信しましたのでご確認ください。
              </p>
              
              <div className="bg-muted/30 rounded-xl p-4 mb-8 text-left border border-border">
                <p className="text-sm text-foreground"><span className="font-medium text-muted-foreground inline-block w-20">日時:</span> {dateLabel} {selectedTime}</p>
                <p className="text-sm text-foreground mt-2"><span className="font-medium text-muted-foreground inline-block w-20">お名前:</span> {form.getValues().name} 様</p>
              </div>

              <Button 
                onClick={onClose}
                variant="outline"
                className="w-full h-12 rounded-xl"
              >
                閉じる
              </Button>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
