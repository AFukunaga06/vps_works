import { useState } from "react";
import { 
  format, 
  addMonths, 
  subMonths, 
  startOfMonth, 
  endOfMonth, 
  startOfWeek, 
  endOfWeek, 
  isSameMonth, 
  isSameDay, 
  eachDayOfInterval,
  isBefore,
  startOfDay
} from "date-fns";
import { ja } from "date-fns/locale";
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon } from "lucide-react";
import { useGetBookings } from "@workspace/api-client-react";

interface BookingCalendarProps {
  onSelectDate: (date: Date) => void;
}

export function BookingCalendar({ onSelectDate }: BookingCalendarProps) {
  const [currentDate, setCurrentDate] = useState(new Date());
  
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth() + 1;
  
  const { data: bookings, isLoading } = useGetBookings({ year, month });

  const nextMonth = () => setCurrentDate(addMonths(currentDate, 1));
  const prevMonth = () => setCurrentDate(subMonths(currentDate, 1));

  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(monthStart);
  const startDate = startOfWeek(monthStart);
  const endDate = endOfWeek(monthEnd);

  const dateFormat = "yyyy-MM-dd";
  const days = eachDayOfInterval({ start: startDate, end: endDate });
  const weekDays = ['日', '月', '火', '水', '木', '金', '土'];

  const today = startOfDay(new Date());

  return (
    <div className="bg-card rounded-3xl shadow-xl shadow-black/5 border border-border/60 overflow-hidden">
      {/* Calendar Header */}
      <div className="px-6 py-6 border-b border-border flex items-center justify-between bg-primary/5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
            <CalendarIcon className="w-5 h-5" />
          </div>
          <h2 className="text-2xl font-display font-bold text-foreground">
            {format(currentDate, "yyyy年 M月", { locale: ja })}
          </h2>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={prevMonth}
            className="p-2 rounded-full hover:bg-primary/10 text-muted-foreground hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20"
            aria-label="前の月"
          >
            <ChevronLeft className="w-6 h-6" />
          </button>
          <button 
            onClick={nextMonth}
            className="p-2 rounded-full hover:bg-primary/10 text-muted-foreground hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20"
            aria-label="次の月"
          >
            <ChevronRight className="w-6 h-6" />
          </button>
        </div>
      </div>

      {/* Calendar Grid */}
      <div className="p-6">
        <div className="grid grid-cols-7 mb-4">
          {weekDays.map((day, i) => (
            <div 
              key={day} 
              className={`text-center font-medium text-sm py-2 ${
                i === 0 ? 'text-destructive/80' : i === 6 ? 'text-blue-500/80' : 'text-muted-foreground'
              }`}
            >
              {day}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7 gap-2 sm:gap-3">
          {days.map((day, i) => {
            const dateStr = format(day, dateFormat);
            const slotInfo = bookings?.find(b => b.date === dateStr);
            
            const isCurrentMonth = isSameMonth(day, monthStart);
            const isPast = isBefore(day, today);
            
            // Availability logic
            const remainingSlots = slotInfo ? slotInfo.legalSlots - slotInfo.legalBooked : 0;
            const hasSlots = slotInfo != null && remainingSlots > 0;
            const isFull = slotInfo != null && remainingSlots <= 0;
            const isAvailable = hasSlots && !isPast && isCurrentMonth;
            
            return (
              <button
                key={day.toString()}
                disabled={!isAvailable}
                onClick={() => onSelectDate(day)}
                className={`
                  relative h-20 sm:h-24 rounded-2xl flex flex-col items-center justify-start pt-2 sm:pt-3 transition-all duration-200 border-2
                  ${!isCurrentMonth ? 'opacity-30 pointer-events-none border-transparent bg-transparent' : ''}
                  ${isSameDay(day, today) ? 'ring-2 ring-primary/30 ring-offset-2' : ''}
                  ${isPast ? 'bg-muted/30 border-transparent text-muted-foreground cursor-not-allowed' : ''}
                  ${isAvailable 
                    ? 'bg-primary/5 border-primary/20 text-foreground hover:border-primary hover:bg-primary/10 hover:-translate-y-0.5 hover:shadow-md cursor-pointer' 
                    : ''}
                  ${isFull && !isPast ? 'bg-accent/5 border-accent/20 text-foreground cursor-not-allowed' : ''}
                  ${!isAvailable && !isFull && !isPast && isCurrentMonth ? 'bg-background border-border text-foreground cursor-not-allowed' : ''}
                `}
              >
                <span className={`text-sm sm:text-base font-medium ${isAvailable ? 'text-primary' : ''}`}>
                  {format(day, 'd')}
                </span>
                
                <div className="mt-auto mb-2 sm:mb-3 flex flex-col items-center justify-center w-full px-1">
                  {isLoading ? (
                    <div className="w-8 h-1.5 bg-muted rounded-full animate-pulse mt-2" />
                  ) : isAvailable ? (
                    <div className="flex flex-col items-center">
                      <span className="w-2 h-2 rounded-full bg-primary mb-1"></span>
                      <span className="text-[10px] sm:text-xs font-bold text-primary whitespace-nowrap hidden sm:block">
                        {remainingSlots}枠 空き
                      </span>
                      <span className="text-[10px] sm:text-xs font-bold text-primary whitespace-nowrap sm:hidden">
                        ○ {remainingSlots}
                      </span>
                    </div>
                  ) : isFull && !isPast ? (
                    <div className="flex flex-col items-center">
                      <span className="w-2 h-2 rounded-full bg-accent mb-1"></span>
                      <span className="text-[10px] sm:text-xs font-medium text-accent whitespace-nowrap hidden sm:block">
                        満席
                      </span>
                      <span className="text-[10px] sm:text-xs font-medium text-accent whitespace-nowrap sm:hidden">
                        ×
                      </span>
                    </div>
                  ) : !isPast && isCurrentMonth ? (
                    <span className="text-muted-foreground text-xs">-</span>
                  ) : null}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Legend */}
      <div className="px-6 py-4 bg-muted/20 border-t border-border flex items-center justify-center gap-6 text-sm text-muted-foreground">
        <div className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-full bg-primary shadow-sm shadow-primary/30"></span>
          <span>予約可能</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-3 h-3 rounded-full bg-accent shadow-sm shadow-accent/30"></span>
          <span>満席</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-muted-foreground/50 font-bold">-</span>
          <span>受付外</span>
        </div>
      </div>
    </div>
  );
}
