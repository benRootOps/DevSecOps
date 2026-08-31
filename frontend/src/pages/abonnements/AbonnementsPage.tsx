// src/pages/abonnements/AbonnementsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { CreditCard, PlusCircle, Check, Zap, Receipt, FileText, ArrowRightLeft, Pencil } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, EmptyState, Input, Select } from "../../components/ui/components";
import { Button, Badge, Skeleton } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const TABS = ["Plans", "Mon abonnement", "Factures & Transactions"];

// ⚠️ Champs déduits du schéma (plans_abonnement / abonnements / factures /
// transactions_paiement) — à ajuster selon AbonnementController si différent.

function formatMontant(v: any) {
  if (v === null || v === undefined || v === "") return "—";
  return new Intl.NumberFormat("fr-FR").format(Number(v)) + " FCFA";
}

export default function AbonnementsPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState(0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <CreditCard size={20} className="text-[#6366F1]" /> Abonnements
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Plans SaaS, facturation et paiements</p>
        </div>
      </div>

      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map((t, i) => (
          <button key={i} onClick={() => setTab(i)}
            className={cn("px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === i ? "bg-[rgba(99,102,241,0.14)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            {t}
          </button>
        ))}
      </div>

      {tab === 0 && <TabPlans qc={qc} />}
      {tab === 1 && <TabMonAbonnement qc={qc} />}
      {tab === 2 && <TabFactures qc={qc} />}
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 0 — Plans
   ══════════════════════════════════════════════════════════════ */
function TabPlans({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<any>(null);
  const [form, setForm] = useState({ nom: "", description: "", prix: "", periodicite: "mensuel", max_utilisateurs: "" });

  const { data: plans, isLoading } = useQuery({
    queryKey: ["plans"],
    queryFn: () => client.get("/plans").then(r => r.data.donnees),
  });

  const saveMut = useMutation({
    mutationFn: (d: typeof form) => {
      const payload = { ...d, prix: Number(d.prix), max_utilisateurs: d.max_utilisateurs ? Number(d.max_utilisateurs) : undefined };
      return editing ? client.put(`/plans/${editing.id}`, payload) : client.post("/plans", payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["plans"] });
      setModalOpen(false); setEditing(null);
      setForm({ nom: "", description: "", prix: "", periodicite: "mensuel", max_utilisateurs: "" });
      toast.success(editing ? "Plan modifié." : "Plan créé.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = plans ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <PermissionGate permission="plans.gerer">
          <Button icon={<PlusCircle size={15} />} onClick={() => { setEditing(null); setModalOpen(true); }}>
            Nouveau plan
          </Button>
        </PermissionGate>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-48 rounded-xl" />)}</div>
      ) : rows.length === 0 ? (
        <Card><CardBody><EmptyState icon={<CreditCard size={24} />} title="Aucun plan" description="Créez le premier plan d'abonnement." /></CardBody></Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {rows.map((p: any) => (
            <Card key={p.id} glow className="relative overflow-hidden">
              <CardBody>
                <div className="flex items-start justify-between mb-3">
                  <span className="text-base font-semibold text-[#F0F2FF]">{p.nom}</span>
                  <PermissionGate permission="plans.gerer">
                    <button
                      onClick={() => { setEditing(p); setForm({ nom: p.nom ?? "", description: p.description ?? "", prix: String(p.prix ?? ""), periodicite: p.periodicite ?? "mensuel", max_utilisateurs: p.max_utilisateurs ? String(p.max_utilisateurs) : "" }); setModalOpen(true); }}
                      className="p-1.5 rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)]">
                      <Pencil size={13} />
                    </button>
                  </PermissionGate>
                </div>
                <div className="flex items-baseline gap-1 mb-3">
                  <span className="text-2xl font-bold font-mono text-[#F0F2FF]">{formatMontant(p.prix)}</span>
                  <span className="text-xs text-[#5C6785]">/ {p.periodicite ?? "mois"}</span>
                </div>
                <p className="text-xs text-[#5C6785] mb-4">{p.description ?? "—"}</p>
                {p.max_utilisateurs && (
                  <div className="flex items-center gap-2 text-xs text-[#8A97B5]">
                    <Check size={13} className="text-[#10B981]" /> Jusqu'à {p.max_utilisateurs} utilisateurs
                  </div>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? "Modifier le plan" : "Nouveau plan"} size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalOpen(false)}>Annuler</Button><Button loading={saveMut.isPending} onClick={() => saveMut.mutate(form)}>{editing ? "Enregistrer" : "Créer"}</Button></>}>
        <div className="space-y-4">
          <Input label="Nom" value={form.nom} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} />
          <Input label="Description" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} />
          <Input label="Prix (FCFA)" type="number" value={form.prix} onChange={e => setForm(f => ({ ...f, prix: e.target.value }))} />
          <Select label="Périodicité" value={form.periodicite} onChange={e => setForm(f => ({ ...f, periodicite: e.target.value }))}
            options={["mensuel", "trimestriel", "annuel"].map(v => ({ value: v, label: v }))} />
          <Input label="Max utilisateurs" type="number" placeholder="Optionnel" value={form.max_utilisateurs} onChange={e => setForm(f => ({ ...f, max_utilisateurs: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 1 — Mon abonnement
   ══════════════════════════════════════════════════════════════ */
function TabMonAbonnement({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [modalSouscrire, setModalSouscrire] = useState(false);
  const [form, setForm] = useState({ plan_id: "", moyen_paiement_id: "" });

  const { data: actif, isLoading } = useQuery({
    queryKey: ["abonnement-actif"],
    queryFn: () => client.get("/abonnements/actif").then(r => r.data.donnees).catch(() => null),
  });

  const { data: plans } = useQuery({
    queryKey: ["plans"],
    queryFn: () => client.get("/plans").then(r => r.data.donnees),
    enabled: modalSouscrire,
  });

  const { data: moyens } = useQuery({
    queryKey: ["moyens-paiement"],
    queryFn: () => client.get("/moyens-paiement").then(r => r.data.donnees),
    enabled: modalSouscrire,
  });

  const souscrireMut = useMutation({
    mutationFn: (d: typeof form) => client.post("/abonnements", { plan_id: Number(d.plan_id), moyen_paiement_id: Number(d.moyen_paiement_id) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["abonnement-actif"] });
      setModalSouscrire(false);
      toast.success("Souscription enregistrée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const statutMut = useMutation({
    mutationFn: ({ id, statut }: { id: number; statut: string }) => client.patch(`/abonnements/${id}/statut`, { statut }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["abonnement-actif"] }); toast.success("Statut mis à jour."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  if (isLoading) return <div className="space-y-4">{Array.from({ length: 2 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}</div>;

  return (
    <div className="space-y-4">
      {!actif ? (
        <Card>
          <CardBody>
            <EmptyState icon={<Zap size={24} />} title="Aucun abonnement actif" description="Souscrivez à un plan pour continuer à utiliser Univora."
              action={
                <PermissionGate permission="abonnements.souscrire">
                  <Button icon={<PlusCircle size={15} />} onClick={() => setModalSouscrire(true)}>Souscrire</Button>
                </PermissionGate>
              } />
          </CardBody>
        </Card>
      ) : (
        <Card glow>
          <CardBody>
            <div className="flex items-start justify-between flex-wrap gap-3">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-lg font-semibold text-[#F0F2FF]">{actif.plan?.nom ?? "Plan"}</span>
                  <Badge color={actif.statut === "actif" ? "#10B981" : "#F59E0B"}>{actif.statut}</Badge>
                </div>
                <p className="text-xs text-[#5C6785]">
                  {actif.date_debut} → {actif.date_fin ?? "en cours"}
                </p>
              </div>
              <span className="text-xl font-bold font-mono text-[#F0F2FF]">{formatMontant(actif.plan?.prix)}</span>
            </div>
            <PermissionGate permission="abonnements.gerer">
              <div className="flex gap-2 mt-4">
                {actif.statut !== "suspendu" && (
                  <Button variant="outline" size="sm" loading={statutMut.isPending} onClick={() => statutMut.mutate({ id: actif.id, statut: "suspendu" })}>
                    Suspendre
                  </Button>
                )}
                {actif.statut !== "actif" && (
                  <Button variant="success" size="sm" loading={statutMut.isPending} onClick={() => statutMut.mutate({ id: actif.id, statut: "actif" })}>
                    Réactiver
                  </Button>
                )}
                <Button variant="danger" size="sm" loading={statutMut.isPending} onClick={() => statutMut.mutate({ id: actif.id, statut: "annule" })}>
                  Annuler
                </Button>
              </div>
            </PermissionGate>
          </CardBody>
        </Card>
      )}

      <Modal open={modalSouscrire} onClose={() => setModalSouscrire(false)} title="Souscrire à un plan" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalSouscrire(false)}>Annuler</Button><Button loading={souscrireMut.isPending} disabled={!form.plan_id || !form.moyen_paiement_id} onClick={() => souscrireMut.mutate(form)}>Souscrire</Button></>}>
        <div className="space-y-4">
          <Select label="Plan" value={form.plan_id} onChange={e => setForm(f => ({ ...f, plan_id: e.target.value }))}
            options={[{ value: "", label: "Sélectionner…" }, ...(plans ?? []).map((p: any) => ({ value: String(p.id), label: `${p.nom} — ${formatMontant(p.prix)}` }))]} />
          <Select label="Moyen de paiement" value={form.moyen_paiement_id} onChange={e => setForm(f => ({ ...f, moyen_paiement_id: e.target.value }))}
            options={[{ value: "", label: "Sélectionner…" }, ...(moyens ?? []).map((m: any) => ({ value: String(m.id), label: m.nom }))]} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 2 — Factures & Transactions
   ══════════════════════════════════════════════════════════════ */
function TabFactures({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [sousTab, setSousTab] = useState<"factures" | "transactions">("factures");
  const [modalFacture, setModalFacture] = useState(false);
  const [abonnementId, setAbonnementId] = useState("");
  const [modalPaiement, setModalPaiement] = useState(false);
  const [formPaiement, setFormPaiement] = useState({ abonnement_id: "", moyen_paiement_id: "", montant: "" });

  const { data: factures, isLoading: facturesLoading } = useQuery({
    queryKey: ["factures"],
    queryFn: () => client.get("/factures").then(r => r.data.donnees),
    enabled: sousTab === "factures",
  });

  const { data: transactions, isLoading: transactionsLoading } = useQuery({
    queryKey: ["transactions"],
    queryFn: () => client.get("/transactions").then(r => r.data.donnees),
    enabled: sousTab === "transactions",
  });

  const genererFactureMut = useMutation({
    mutationFn: (id: string) => client.post(`/abonnements/${id}/facture`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["factures"] }); setModalFacture(false); setAbonnementId(""); toast.success("Facture générée."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const initierMut = useMutation({
    mutationFn: (d: typeof formPaiement) => client.post("/paiements/initier", { abonnement_id: Number(d.abonnement_id), moyen_paiement_id: Number(d.moyen_paiement_id), montant: Number(d.montant) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["transactions"] });
      setModalPaiement(false);
      setFormPaiement({ abonnement_id: "", moyen_paiement_id: "", montant: "" });
      toast.success("Paiement initié.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const confirmerMut = useMutation({
    mutationFn: (id: number) => client.patch(`/paiements/${id}/confirmer`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["transactions"] }); toast.success("Paiement confirmé."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
          {(["factures", "transactions"] as const).map(s => (
            <button key={s} onClick={() => setSousTab(s)}
              className={cn("px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all capitalize",
                sousTab === s ? "bg-[rgba(99,102,241,0.14)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
              {s}
            </button>
          ))}
        </div>
        <div className="flex gap-2">
          <PermissionGate permission="factures.generer">
            <Button variant="outline" size="sm" icon={<FileText size={13} />} onClick={() => setModalFacture(true)}>Générer une facture</Button>
          </PermissionGate>
          <PermissionGate permission="paiements.initier">
            <Button size="sm" icon={<ArrowRightLeft size={13} />} onClick={() => setModalPaiement(true)}>Initier un paiement</Button>
          </PermissionGate>
        </div>
      </div>

      {sousTab === "factures" ? (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-[rgba(255,255,255,0.05)]">
                  {["Numéro", "Plan", "Montant", "Date", "Statut"].map(h => (
                    <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {facturesLoading
                  ? Array.from({ length: 4 }).map((_, i) => <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">{[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}</tr>)
                  : (factures ?? []).length === 0
                  ? <tr><td colSpan={5}><EmptyState icon={<FileText size={24} />} title="Aucune facture" /></td></tr>
                  : factures.map((f: any) => (
                      <tr key={f.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3 text-xs font-mono text-[#8A97B5]">{f.numero ?? `#${f.id}`}</td>
                        <td className="px-6 py-3 text-sm text-[#F0F2FF]">{f.abonnement?.plan?.nom ?? "—"}</td>
                        <td className="px-6 py-3 text-sm font-mono font-bold text-[#6366F1]">{formatMontant(f.montant)}</td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{f.date_emission ?? f.cree_le}</td>
                        <td className="px-6 py-3"><Badge color={f.statut === "payee" ? "#10B981" : "#F59E0B"}>{f.statut}</Badge></td>
                      </tr>
                    ))}
              </tbody>
            </table>
          </div>
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-[rgba(255,255,255,0.05)]">
                  {["Référence", "Montant", "Moyen", "Statut", "Date", ""].map(h => (
                    <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {transactionsLoading
                  ? Array.from({ length: 4 }).map((_, i) => <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">{[1, 2, 3, 4, 5, 6].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}</tr>)
                  : (transactions ?? []).length === 0
                  ? <tr><td colSpan={6}><EmptyState icon={<Receipt size={24} />} title="Aucune transaction" /></td></tr>
                  : transactions.map((t: any) => (
                      <tr key={t.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3 text-xs font-mono text-[#8A97B5]">{t.reference ?? `#${t.id}`}</td>
                        <td className="px-6 py-3 text-sm font-mono font-bold text-[#6366F1]">{formatMontant(t.montant)}</td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{t.moyen_paiement?.nom ?? "—"}</td>
                        <td className="px-6 py-3"><Badge color={t.statut === "confirme" ? "#10B981" : t.statut === "echec" ? "#EF4444" : "#F59E0B"}>{t.statut}</Badge></td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{t.cree_le}</td>
                        <td className="px-6 py-3 text-right">
                          <PermissionGate permission="paiements.initier">
                            {t.statut === "en_attente" && (
                              <Button variant="outline" size="sm" loading={confirmerMut.isPending} onClick={() => confirmerMut.mutate(t.id)}>Confirmer</Button>
                            )}
                          </PermissionGate>
                        </td>
                      </tr>
                    ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      <Modal open={modalFacture} onClose={() => setModalFacture(false)} title="Générer une facture" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalFacture(false)}>Annuler</Button><Button loading={genererFactureMut.isPending} disabled={!abonnementId} onClick={() => genererFactureMut.mutate(abonnementId)}>Générer</Button></>}>
        <Input label="Abonnement ID" type="number" value={abonnementId} onChange={e => setAbonnementId(e.target.value)} />
      </Modal>

      <Modal open={modalPaiement} onClose={() => setModalPaiement(false)} title="Initier un paiement" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalPaiement(false)}>Annuler</Button><Button loading={initierMut.isPending} onClick={() => initierMut.mutate(formPaiement)}>Initier</Button></>}>
        <div className="space-y-4">
          <Input label="Abonnement ID" type="number" value={formPaiement.abonnement_id} onChange={e => setFormPaiement(f => ({ ...f, abonnement_id: e.target.value }))} />
          <Input label="Moyen de paiement ID" type="number" value={formPaiement.moyen_paiement_id} onChange={e => setFormPaiement(f => ({ ...f, moyen_paiement_id: e.target.value }))} />
          <Input label="Montant (FCFA)" type="number" value={formPaiement.montant} onChange={e => setFormPaiement(f => ({ ...f, montant: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
