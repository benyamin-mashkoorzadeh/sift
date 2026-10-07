import { MessageCircleMore } from "lucide-react";

interface WidgetLauncherProps {
  onOpen: () => void;
  unavailable: boolean;
}

export function WidgetLauncher({ onOpen, unavailable }: WidgetLauncherProps) {
  return (
    <button
      aria-label={unavailable ? "Open customer support status" : "Open customer support"}
      className="grid size-14 cursor-pointer place-items-center rounded-[15px] border border-primary bg-primary text-surface shadow-[0_8px_24px_rgba(33,29,30,.18)] transition-[background,border-color,transform] duration-160 hover:-translate-y-px hover:border-primary-hover hover:bg-primary-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0"
      onClick={onOpen}
      type="button"
    >
      <MessageCircleMore aria-hidden="true" size={25} strokeWidth={1.9} />
    </button>
  );
}
