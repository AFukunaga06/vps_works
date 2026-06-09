import { useState } from "react";
import { motion } from "framer-motion";
import { Scale, FileText, CheckCircle, ChevronDown, Phone, MapPin, Clock } from "lucide-react";
import { BookingCalendar } from "@/components/booking-calendar";
import { BookingModal } from "@/components/booking-modal";

export default function Home() {
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);

  const scrollToCalendar = () => {
    document.getElementById("reservation")?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <div className="min-h-screen bg-background font-sans selection:bg-primary/20 selection:text-primary">
      {/* Navigation */}
      <nav className="fixed top-0 inset-x-0 h-16 bg-background/80 backdrop-blur-md border-b border-border/50 z-40 flex items-center">
        <div className="max-w-6xl w-full mx-auto px-4 sm:px-6 flex items-center justify-between">
          <div className="flex items-center gap-2 text-primary font-display font-bold text-xl">
            <Scale className="w-6 h-6" />
            <span>フクの相談窓口</span>
          </div>
          <button 
            onClick={scrollToCalendar}
            className="text-sm font-bold text-primary hover:text-primary/80 transition-colors"
          >
            ご予約はこちら
          </button>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        {/* Background Image & Overlay */}
        <div className="absolute inset-0 z-0">
          <img 
            src={`${import.meta.env.BASE_URL}images/hero-bg.png`} 
            alt="Calm abstract background" 
            className="w-full h-full object-cover opacity-30"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-background/40 via-background/80 to-background" />
        </div>

        <div className="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, ease: "easeOut" }}
          >
            <span className="inline-block py-1.5 px-4 rounded-full bg-primary/10 text-primary font-bold text-sm mb-6 border border-primary/20">
              専門家に相談する前に
            </span>
            <h1 className="text-4xl md:text-5xl lg:text-6xl font-display font-bold text-foreground leading-[1.3] tracking-tight mb-6">
              まずは状況を<br className="md:hidden" />整理しませんか？
            </h1>
            <p className="text-base md:text-lg text-muted-foreground max-w-2xl mx-auto leading-relaxed mb-10">
              法律に関するお困りごとについて、弁護士・司法書士などの専門家に相談する前のサポートを行います。
              状況整理、時系列整理、質問事項の整理などを丁寧にお手伝いします。
            </p>

            <button
              onClick={scrollToCalendar}
              className="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-lg shadow-xl shadow-primary/20 hover:shadow-2xl hover:shadow-primary/30 hover:-translate-y-1 active:translate-y-0 transition-all duration-300"
            >
              無料相談を予約する
              <ChevronDown className="w-5 h-5 animate-bounce" />
            </button>
          </motion.div>
        </div>
      </section>

      {/* Service Detail Section */}
      <section className="py-20 bg-card border-y border-border">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid lg:grid-cols-2 gap-16 items-center">
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5 }}
            >
              <h2 className="text-3xl font-display font-bold text-foreground mb-6 flex items-center gap-3">
                <span className="w-12 h-1 bg-primary rounded-full inline-block"></span>
                法律相談（相談前整理サポート）
              </h2>
              <p className="text-muted-foreground leading-relaxed mb-8">
                「弁護士に相談したいけど、何をどう話せばいいかわからない」「状況が複雑すぎて、うまく伝えられるか不安」といったお悩みに寄り添います。専門家との面談時間を有効に使えるよう、事前の準備をサポートします。
              </p>

              <div className="space-y-4 mb-8">
                {[
                  "相続・遺産の相談前準備",
                  "家族間トラブルの状況整理",
                  "契約や請求に関する不安の整理",
                  "弁護士・司法書士へ渡す「質問メモ」の作成支援",
                ].map((item, idx) => (
                  <div key={idx} className="flex items-start gap-3">
                    <CheckCircle className="w-6 h-6 text-primary shrink-0" />
                    <span className="text-foreground font-medium">{item}</span>
                  </div>
                ))}
              </div>

              <div className="bg-primary/5 rounded-2xl p-6 border border-primary/10">
                <h3 className="font-bold text-primary mb-4 flex items-center gap-2">
                  <FileText className="w-5 h-5" />
                  ご利用料金
                </h3>
                <ul className="space-y-3">
                  <li className="flex justify-between items-center pb-3 border-b border-primary/10">
                    <span className="font-medium">初回相談 (30〜45分)</span>
                    <span className="font-bold text-lg text-primary">無料</span>
                  </li>
                  <li className="flex justify-between items-center pt-1">
                    <span className="font-medium text-muted-foreground">2回目以降 (45分)</span>
                    <span className="font-bold text-foreground">1,500円<span className="text-xs font-normal ml-1">(税込)</span></span>
                  </li>
                </ul>
                <p className="text-xs text-muted-foreground mt-4">※ 2回目以降は前払い制となります。</p>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, x: 20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="relative"
            >
              <div className="absolute inset-0 bg-gradient-to-tr from-primary/20 to-transparent rounded-3xl transform translate-x-4 translate-y-4 -z-10" />
              <img 
                src={`${import.meta.env.BASE_URL}images/consultation.png`}
                alt="Consultation arrangement" 
                className="rounded-3xl shadow-2xl w-full h-auto object-cover border border-border/50"
              />
            </motion.div>
          </div>
        </div>
      </section>

      {/* Reservation Section */}
      <section id="reservation" className="py-24 bg-background">
        <div className="max-w-5xl mx-auto px-4 sm:px-6">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-display font-bold text-foreground mb-4">ご予約カレンダー</h2>
            <p className="text-muted-foreground">
              ご希望の日付を選択し、空き時間をご確認ください。<br />
              <span className="text-primary font-medium">緑色の「〇 N枠 空き」</span>が表示されている日がご予約可能です。
            </p>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <BookingCalendar onSelectDate={setSelectedDate} />
          </motion.div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-foreground text-background py-16">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid md:grid-cols-2 gap-12 border-b border-background/10 pb-12 mb-8">
            <div>
              <div className="flex items-center gap-2 font-display font-bold text-2xl mb-6">
                <Scale className="w-7 h-7 text-primary-foreground" />
                <span>フクの相談窓口</span>
              </div>
              <p className="text-background/70 leading-relaxed max-w-sm">
                あなたの不安を整理し、専門家への架け橋となるサポートを提供します。
              </p>
            </div>

            <div className="space-y-4 text-background/80">
              <h4 className="font-bold text-white mb-4">インフォメーション</h4>
              <div className="flex items-start gap-3">
                <Clock className="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                  <p>営業時間：月〜土 10:00〜18:00</p>
                  <p className="text-sm text-background/50 mt-1">（日曜・祝日 定休）</p>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <MapPin className="w-5 h-5 shrink-0 mt-0.5" />
                <p>オンライン相談（Zoom / 電話）対応</p>
              </div>
              <div className="flex items-start gap-3">
                <Phone className="w-5 h-5 shrink-0 mt-0.5" />
                <p>03-XXXX-XXXX <span className="text-xs text-background/50 ml-1">(営業時間内のみ)</span></p>
              </div>
            </div>
          </div>

          <div className="text-sm text-background/50 space-y-2 mb-8">
            <p>※ 初回無料相談は事前予約が必要です。</p>
            <p>※ 2回目以降は事前入金確認後に予約確定となります。</p>
            <p>※ 当窓口は法的トラブルの整理を目的としており、法的な見解や代理交渉などの弁護士業務は行えません。</p>
          </div>

          <div className="text-center text-background/40 text-sm">
            &copy; {new Date().getFullYear()} フクの相談窓口. All rights reserved.
          </div>
        </div>
      </footer>

      {/* Booking Modal */}
      <BookingModal 
        selectedDate={selectedDate} 
        onClose={() => setSelectedDate(null)} 
      />
    </div>
  );
}
