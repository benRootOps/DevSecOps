// src/pages/etablissements/EtablissementsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Building2, Search, Power, MapPin } from "lucide-react";
import { Card, EmptyState, StatCard } from "../../components/ui/components";
import { Badge, Skeleton, Button } from "../../components/ui/atoms";
import { toast } from "sonner";
import client from "../../api/client";

export default function EtablissementsPage() {
  const qc = useQueryClient();
  const [search, setSearch] = useState("");

  const { data: etablissements, isLoading } = useQuery({
    queryKey: ["etablissements", search],
    queryFn: () => client.get("/etablissements", { params: { recherche: search || undefined } }).then(r => r.data.donnees),
  });

  const { data: stats } = useQuery({
    queryKey: ["etablissements-statistiques"],
    queryFn: () => client.get("/etablissements/statistiques").then(r => r.data.donnees),
  });

  const toggleMut = useMutation({
    mutationFn: (id: number) => client.patch(`/etablissements/${id}/toggle-actif`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["etablissements"] }); toast.success("Statut mis à jour."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = etablissements?.data ?? etablissements ?? [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
          <Building2 size={20} className="text-[#8B5CF6]" /> Établissements
        </h1>
        <p className="text-sm text-[#5C6785] mt-0.5">Universités inscrites sur la plateforme</p>
      </div>

      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <StatCard label="Total" value={stats.total ?? rows.length} icon={<Building2 size={18} />} color="#8B5CF6" />
          <StatCard label="Actifs" value={stats.actifs ?? "—"} icon={<Power size={18} />} color="#10B981" />
          <StatCard label="Inactifs" value={stats.inactifs ?? "—"} icon={<Power size={18} />} color="#EF4444" />
        </div>
      )}

      <div className="relative max-w-xs">
        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#5C6785]" />
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un établissement…"
          className="w-full pl-9 pr-4 py-2 text-sm bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#F0F2FF] placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.4)]"
        />
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Établissement", "Ville", "Email", "Statut", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 5 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<Building2 size={24} />} title="Aucun établissement" description="Les établissements apparaissent ici une fois leur demande validée." /></td></tr>
                : rows.map((etab: any) => (
                    <tr key={etab.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                      <td className="px-6 py-3">
                        <div className="flex items-center gap-3">
                          {etab.logo_url
                            ? <img src={etab.logo_url} alt="" className="w-8 h-8 rounded-lg object-cover" />
                            : <div className="w-8 h-8 rounded-lg bg-[rgba(139,92,246,0.12)] flex items-center justify-center text-[#8B5CF6] text-xs font-bold">{etab.nom?.[0]}</div>}
                          <span className="text-sm text-[#F0F2FF] font-medium">{etab.nom}</span>
                        </div>
                      </td>
                      <td className="px-6 py-3 text-xs text-[#5C6785]">
                        <span className="inline-flex items-center gap-1"><MapPin size={11} /> {etab.ville}, {etab.pays}</span>
                      </td>
                      <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{etab.email}</td>
                      <td className="px-6 py-3">
                        <Badge color={etab.est_actif ? "#10B981" : "#EF4444"}>{etab.est_actif ? "Actif" : "Inactif"}</Badge>
                      </td>
                      <td className="px-6 py-3 text-right">
                        <Button variant="outline" size="sm" icon={<Power size={13} />}
                          loading={toggleMut.isPending} onClick={() => toggleMut.mutate(etab.id)}>
                          {etab.est_actif ? "Désactiver" : "Activer"}
                        </Button>
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
