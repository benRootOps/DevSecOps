// src/pages/utilisateurs/EtudiantsPage.tsx
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Users, Search } from "lucide-react";
import { Card, EmptyState } from "../../components/ui/components";
import { Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import client from "../../api/client";

export default function EtudiantsPage() {
  const [search, setSearch] = useState("");

  const { data: etudiants, isLoading } = useQuery({
    queryKey: ["etudiants", search],
    queryFn: () => client.get("/etudiants", { params: { recherche: search || undefined } }).then(r => r.data.donnees),
  });

  const rows: any[] = etudiants?.data ?? etudiants ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <Users size={20} className="text-[#06B6D4]" /> Étudiants
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">{rows.length} étudiant(s)</p>
        </div>
      </div>

      <div className="relative max-w-xs">
        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#5C6785]" />
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un étudiant…"
          className="w-full pl-9 pr-4 py-2 text-sm bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#F0F2FF] placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.4)]"
        />
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Étudiant", "Matricule", "Classe", "Email", "Statut"].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 6 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<Users size={24} />} title="Aucun étudiant" description="Les étudiants apparaissent ici une fois leur demande de compte validée." /></td></tr>
                : rows.map((e: any) => (
                    <tr key={e.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                      <td className="px-6 py-3">
                        <div className="flex items-center gap-3">
                          <Avatar src={e.photo_url} nom={e.nom} prenom={e.prenom} size="sm" />
                          <span className="text-sm text-[#F0F2FF]">{e.prenom} {e.nom}</span>
                        </div>
                      </td>
                      <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{e.matricule}</td>
                      <td className="px-6 py-3">
                        {e.inscription_active?.classe?.nom
                          ? <Badge color="#06B6D4">{e.inscription_active.classe.nom}</Badge>
                          : <span className="text-xs text-[#5C6785]">Non inscrit</span>}
                      </td>
                      <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{e.email ?? "—"}</td>
                      <td className="px-6 py-3">
                        <Badge color={e.est_actif ? "#10B981" : "#EF4444"}>{e.est_actif ? "Actif" : "Inactif"}</Badge>
                      </td>
                    </tr>
                  ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
