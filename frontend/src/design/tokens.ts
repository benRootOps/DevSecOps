export const colors = {
  bg: { primary:"#0A0C14", surface:"#111420", glass:"rgba(17,20,32,0.6)", elevated:"#161925", hover:"#1A1E2E" },
  border: { default:"rgba(255,255,255,0.06)", subtle:"rgba(255,255,255,0.03)", active:"rgba(124,106,247,0.4)" },
  accent: { primary:"#7C6AF7", hover:"#8B7CFA", muted:"rgba(124,106,247,0.12)", glow:"rgba(124,106,247,0.25)" },
  modules: { emploi:"#06B6D4", presences:"#10B981", notes:"#A78BFA", financier:"#F59E0B", abonnements:"#6366F1", deliber:"#EC4899", documents:"#14B8A6", demandes:"#F97316" },
  success:"#10B981", error:"#EF4444", warning:"#F59E0B", info:"#06B6D4",
  text: { primary:"#F0F2FF", secondary:"#8A97B5", muted:"#5C6785" },
} as const;
export const bgGradient = `radial-gradient(ellipse at 20% 50%, rgba(124,106,247,0.06) 0%, transparent 60%), radial-gradient(ellipse at 80% 20%, rgba(6,182,212,0.04) 0%, transparent 50%), #0A0C14`;
