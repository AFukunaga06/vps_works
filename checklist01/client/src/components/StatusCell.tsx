import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";

interface StatusCellProps {
  value: string;
  onChange: (value: string) => void;
  isLoading?: boolean;
}

export function StatusCell({ value, onChange, isLoading }: StatusCellProps) {
  // Determine color based on value for the trigger text
  const getStatusColor = (val: string) => {
    switch (val) {
      case "◎": return "text-green-600 font-bold";
      case "〇": return "text-blue-600 font-bold";
      case "△": return "text-orange-500 font-bold";
      case "ー": return "text-gray-300";
      default: return "text-gray-400";
    }
  };

  return (
    <div className="flex justify-center items-center h-full w-full">
      <Select 
        value={value} 
        onValueChange={onChange} 
        disabled={isLoading}
      >
        <SelectTrigger 
          className={cn(
            "w-full h-8 border-transparent hover:bg-accent/50 focus:ring-0 focus:ring-offset-0 px-1 justify-center text-center font-mono text-lg transition-colors",
            getStatusColor(value)
          )}
        >
          <SelectValue placeholder="ー" />
        </SelectTrigger>
        <SelectContent align="center" className="min-w-[4rem]">
          <SelectItem value="◎" className="text-green-600 font-bold justify-center cursor-pointer">◎</SelectItem>
          <SelectItem value="〇" className="text-blue-600 font-bold justify-center cursor-pointer">〇</SelectItem>
          <SelectItem value="△" className="text-orange-500 font-bold justify-center cursor-pointer">△</SelectItem>
          <SelectItem value="ー" className="text-gray-400 justify-center cursor-pointer">ー</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );
}
