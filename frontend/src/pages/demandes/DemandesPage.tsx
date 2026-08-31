// src/pages/demandes/DemandesPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { ClipboardList, CheckCircle, XCircle, Eye, Building2, GraduationCap, User } from "lucide-react";
import { Card, CardHeader, CardBody } from "../../components/ui/components";
import { Button } from "../../components/ui/atoms";
import { Badge } from "../../components/ui/atoms";
import { Skeleton } from "../../components/ui/atoms";
import { EmptyState } from "../../components/ui/components";
import { Modal } from "../../components/ui/components";
import { Input } from "../../components/ui/components";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const TYPE_CONFIG: Record<string, { label: string; icon: React.ElementType; color: string }> = {
  etablissement: { label: "Établissement", icon: Building2,    color: "#7C6AF7" },
  enseignant:    { label: "Enseignant",    icon: GraduationCap, color: "#06B6D4" },
  etudiant:      { label: "Étudiant",      icon: User,          color: "#10B981" },
};

const STATUT_CONFIG: Record<string, { label: string; color: string }> = {
  en_attente: { label: "En attente", color: "#F97316" },
  valide:     { label: "Validé",     color: "#10B981" },
  rejete:     { label: "Rejeté",     color: "#EF4444" },
};

const TABS = [
  { key: "en_attente", label: "En attente" },
  { key: "valide",     label: "Validées"   },
  { key: "rejete",     label: "Rejetées"   },
  { key: "",           label: "Toutes"     },
];

export default function DemandesPage() {
  const qc = useQueryClient();
  const [statut,  setStatut]  = useState("en_attente");
  const [detail,  setDetail]  = useState<any>(null);
  const [motif,   setMotif]   = useState("");
  const [rejectId, setRejectId] = useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["demandes", statut],
    queryFn: () =>
      client.get("/demandes", { params: { statut: statut || undefined, par_page: 20 } })
            .then(r => r.data.donnees),
  });

  const validerMut = useMutation({
    mutationFn: (id: number) => client.post(`/demandes/${id}/valider`, { mot_de_passe: "TempPass@2025!" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["demandes"] });
      setDetail(null);
      toast.success("Demande validée. Compte créé et credentials envoyés.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rejeterMut = useMutation({
    mutationFn: ({ id, motif }: { id: number; motif: string }) =>
      client.post(`/demandes/${id}/rejeter`, { motif }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["demandes"] });
      setRejectId(null);
      setMotif("");
      toast.success("Demande rejetée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div>
        <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
          <ClipboardList size={20} className="text-[#F97316]" />
          Demandes de compte
        </h1>
        <p className="text-sm text-[#5C6785] mt-0.5">
          Validez ou rejetez les demandes d'accès à la plateforme.
        </p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map(t => (
          <button
            key={t.key}
            onClick={() => setStatut(t.key)}
            className={cn(
              "px-4 py-2 text-xs font-medium rounded-lg transition-all duration-150",
              statut === t.key
                ? "bg-[rgba(124,106,247,0.15)] text-[#F0F2FF] shadow-[0_0_12px_rgba(124,106,247,0.15)]"
                : "text-[#5C6785] hover:text-[#8A97B5]"
            )}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Liste */}
      <div className="space-y-3">
        {isLoading
          ? Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl p-5">
                <div className="flex items-center gap-4">
                  <Skeleton className="w-11 h-11 rounded-xl" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="h-3 w-32" />
                  </div>
                </div>
              </div>
            ))
          : data?.data?.length === 0
          ? (
            <div className="bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl">
              <EmptyState
                icon={<ClipboardList size={24} />}
                title="Aucune demande"
                description="Toutes les demandes ont été traitées."
              />
            </div>
          )
          : data?.data?.map((d: any) => {
            const cfg    = TYPE_CONFIG[d.type_demande];
            const stCfg  = STATUT_CONFIG[d.statut];
            const IconCmp = cfg?.icon ?? User;

            return (
              <div
                key={d.id}
                className="bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl p-5 hover:border-[rgba(124,106,247,0.2)] transition-all group"
              >
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-4">
                    <div
                      className="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                      style={{ background: `${cfg?.color ?? "#7C6AF7"}15`, color: cfg?.color ?? "#7C6AF7" }}
                    >
                      <IconCmp size={20} />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-[#F0F2FF]">
                          {d.donnees?.nom ?? d.donnees?.admin_nom ?? "—"}
                          {d.donnees?.prenom && ` ${d.donnees.prenom}`}
                        </span>
                        <Badge color={cfg?.color ?? "#7C6AF7"}>{cfg?.label}</Badge>
                        <Badge color={stCfg?.color ?? "#8A97B5"}>{stCfg?.label}</Badge>
                      </div>
                      <div className="text-xs text-[#5C6785] mt-0.5">
                        {d.donnees?.email ?? d.donnees?.admin_email} ·
                        Soumis le {new Date(d.soumis_le).toLocaleDateString("fr-FR")}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 flex-shrink-0">
                    <button
                      onClick={() => setDetail(d)}
                      className="p-2 rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)] transition-all"
                    >
                      <Eye size={15} />
                    </button>
                    {d.statut === "en_attente" && (
                      <PermissionGate permission="utilisateurs.creer">
                        <Button
                          size="sm"
                          variant="success"
                          icon={<CheckCircle size={13} />}
                          loading={validerMut.isPending}
                          onClick={() => validerMut.mutate(d.id)}
                        >
                          Valider
                        </Button>
                        <Button
                          size="sm"
                          variant="danger"
                          icon={<XCircle size={13} />}
                          onClick={() => { setRejectId(d.id); setMotif(""); }}
                        >
                          Rejeter
                        </Button>
                      </PermissionGate>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
      </div>

      {/* Modal détail */}
      <Modal open={!!detail} onClose={() => setDetail(null)} title="Détail de la demande" size="md">
        {detail && (
          <div className="space-y-3">
            {Object.entries(detail.donnees ?? {}).map(([k, v]) => (
              <div key={k} className="flex justify-between items-center py-2 border-b border-[rgba(255,255,255,0.04)]">
                <span className="text-xs text-[#5C6785] capitalize">{k.replace(/_/g, " ")}</span>
                <span className="text-sm text-[#F0F2FF] font-mono">{String(v)}</span>
              </div>
            ))}
          </div>
        )}
      </Modal>

      {/* Modal rejet */}
      <Modal
        open={!!rejectId}
        onClose={() => setRejectId(null)}
        title="Rejeter la demande"
        size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setRejectId(null)}>Annuler</Button>
            <Button
              variant="danger"
              loading={rejeterMut.isPending}
              onClick={() => rejectId && rejeterMut.mutate({ id: rejectId, motif })}
            >
              Confirmer le rejet
            </Button>
          </>
        }
      >
        <Input
          label="Motif du rejet"
          value={motif}
          onChange={e => setMotif(e.target.value)}
          placeholder="Ex: Dossier incomplet, veuillez resoumettre."
        />
      </Modal>
    </div>
  );
}
