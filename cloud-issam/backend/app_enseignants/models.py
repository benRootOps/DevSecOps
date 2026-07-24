from django.db import models
from django.contrib.auth.models import AbstractUser
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone


# UTILISATEUR PERSONNALISÉ
class CustomUser(AbstractUser):
    """Modèle utilisateur de base pour le système"""
    ROLE_CHOICES = (
        ("enseignant", "Enseignant"),
        ("administrateur", "Administrateur"),
    )
    role = models.CharField(max_length=20, choices=ROLE_CHOICES)

    def __str__(self):
        return f"{self.username} ({self.get_role_display()})"

    class Meta:
        verbose_name = "Utilisateur"
        verbose_name_plural = "Utilisateurs"


# ENSEIGNANT
class Enseignant(models.Model):
    user = models.OneToOneField(CustomUser, on_delete=models.CASCADE, related_name='enseignant_profile')

    nom = models.CharField(max_length=100)
    prenom = models.CharField(max_length=100)
    date_naissance = models.DateField(null=True, blank=True)
    lieu_naissance = models.CharField(max_length=150, null=True, blank=True)

    is_verified = models.BooleanField(default=False)
    is_active = models.BooleanField(default=False)

    created_at = models.DateTimeField(auto_now_add=True, null=True, blank=True)
    updated_at = models.DateTimeField(auto_now=True, null=True, blank=True)

    class Meta:
        db_table = 'enseignants'
        verbose_name = 'Enseignant'
        verbose_name_plural = 'Enseignants'
        ordering = ['nom', 'prenom']

    def __str__(self):
        return f"{self.prenom} {self.nom}"


# ADMINISTRATEUR
class Administrateur(models.Model):
    """Profil administrateur lié à un utilisateur"""
    user = models.OneToOneField(CustomUser, on_delete=models.CASCADE, related_name='admin_profile')

    nom = models.CharField(max_length=100)
    prenom = models.CharField(max_length=100)
    date_naissance = models.DateField(null=True, blank=True)
    lieu_naissance = models.CharField(max_length=150, null=True, blank=True)

    is_approved = models.BooleanField(default=False)

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'administrateurs'
        verbose_name = 'Administrateur'
        verbose_name_plural = 'Administrateurs'
        ordering = ['nom', 'prenom']

    def __str__(self):
        return f"{self.prenom} {self.nom}"


# NIVEAU
class Niveau(models.Model):
    """Niveaux d'études: BTS1, BTS2, LICENCE, etc."""
    nom = models.CharField(max_length=50, unique=True)
    description = models.TextField(null=True, blank=True)
    ordre = models.IntegerField(default=0, help_text="Ordre d'affichage")

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'niveaux'
        verbose_name = 'Niveau'
        verbose_name_plural = 'Niveaux'
        ordering = ['ordre', 'nom']

    def __str__(self):
        return self.nom


# FILIÈRE
class Filiere(models.Model):
    nom = models.CharField(max_length=100, unique=True)
    code = models.CharField(max_length=20, unique=True, null=True, blank=True)
    description = models.TextField(null=True, blank=True)

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'filieres'
        verbose_name = 'Filière'
        verbose_name_plural = 'Filières'
        ordering = ['nom']

    def __str__(self):
        return self.nom


# SPÉCIALITÉ
class Specialite(models.Model):
    """Spécialités liées aux filières"""
    nom = models.CharField(max_length=100)
    filiere = models.ForeignKey(Filiere, on_delete=models.CASCADE, related_name='specialites')
    description = models.TextField(null=True, blank=True)

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'specialites'
        verbose_name = 'Spécialité'
        verbose_name_plural = 'Spécialités'
        unique_together = ['nom', 'filiere']
        ordering = ['filiere', 'nom']

    def __str__(self):
        return f"{self.nom} ({self.filiere.nom})"


# MATIÈRE
class Matiere(models.Model):
    """Matières enseignées"""
    nom = models.CharField(max_length=150)
    code = models.CharField(max_length=20, unique=True, null=True, blank=True)
    description = models.TextField(null=True, blank=True)
    coefficient = models.DecimalField(max_digits=4, decimal_places=2, default=1.0)

    niveau = models.ForeignKey(Niveau, on_delete=models.CASCADE, related_name='matieres')
    filiere = models.ForeignKey(Filiere, on_delete=models.CASCADE, related_name='matieres')
    specialites = models.ManyToManyField(Specialite, related_name='matieres', blank=True)

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'matieres'
        verbose_name = 'Matière'
        verbose_name_plural = 'Matières'
        unique_together = ['nom', 'niveau', 'filiere']
        ordering = ['niveau', 'filiere', 'nom']

    def __str__(self):
        return f"{self.nom} - {self.niveau.nom} - {self.filiere.nom}"


# ÉTUDIANT
class Etudiant(models.Model):
    """Étudiants inscrits"""
    matricule = models.CharField(max_length=50, unique=True)

    nom = models.CharField(max_length=100)
    prenom = models.CharField(max_length=100)
    date_naissance = models.DateField(null=True, blank=True)
    lieu_naissance = models.CharField(max_length=150, null=True, blank=True)

    email = models.EmailField(unique=True)
    telephone = models.CharField(max_length=20, null=True, blank=True)

    niveau = models.ForeignKey(Niveau, on_delete=models.CASCADE, related_name='etudiants')
    filiere = models.ForeignKey(Filiere, on_delete=models.CASCADE, related_name='etudiants')
    specialite = models.ForeignKey(Specialite, on_delete=models.CASCADE, related_name='etudiants')

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'etudiants'
        verbose_name = 'Étudiant'
        verbose_name_plural = 'Étudiants'
        ordering = ['nom', 'prenom']

    def __str__(self):
        return f"{self.prenom} {self.nom} ({self.matricule})"


# LIAISON ENSEIGNANT - NIVEAU
class EnseignantNiveau(models.Model):
    enseignant = models.ForeignKey(Enseignant, on_delete=models.CASCADE, related_name='niveaux_enseignes')
    niveau = models.ForeignKey(Niveau, on_delete=models.CASCADE, related_name='enseignants')
    filiere = models.ForeignKey(Filiere, on_delete=models.CASCADE, related_name='enseignants_niveau')

    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'enseignant_niveaux'
        verbose_name = 'Enseignant Niveau'
        verbose_name_plural = 'Enseignant Niveaux'
        unique_together = ['enseignant', 'niveau', 'filiere']

    def __str__(self):
        return f"{self.enseignant} - {self.niveau} - {self.filiere}"


# LIAISON ENSEIGNANT - SPÉCIALITÉ
class EnseignantSpecialite(models.Model):
    enseignant = models.ForeignKey(Enseignant, on_delete=models.CASCADE, related_name='specialites_enseignees')
    specialite = models.ForeignKey(Specialite, on_delete=models.CASCADE, related_name='enseignants')

    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'enseignant_specialites'
        verbose_name = 'Enseignant Spécialité'
        verbose_name_plural = 'Enseignant Spécialités'
        unique_together = ['enseignant', 'specialite']

    def __str__(self):
        return f"{self.enseignant} - {self.specialite}"


# LIAISON ENSEIGNANT - MATIÈRE
class EnseignantMatiere(models.Model):
    enseignant = models.ForeignKey(Enseignant, on_delete=models.CASCADE, related_name='matieres_enseignees')
    matiere = models.ForeignKey(Matiere, on_delete=models.CASCADE, related_name='enseignants')

    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'enseignant_matieres'
        verbose_name = 'Enseignant Matière'
        verbose_name_plural = 'Enseignant Matières'
        unique_together = ['enseignant', 'matiere']

    def __str__(self):
        return f"{self.enseignant} - {self.matiere}"


# ============================================================
# PÉRIODE DE SAISIE — contrôle quelles sections sont ouvertes
# ============================================================
class PeriodeSaisie(models.Model):
    """
    Définit quelle(s) section(s) sont ouvertes à la saisie.

    Sections disponibles :
      - CC1  : contrôle continu du semestre 1 (ouvert dès le début d'année)
      - SN1  : session normale semestre 1 (activée manuellement par l'admin)
      - CC2  : contrôle continu du semestre 2 (ouvert après clôture SN1)
      - SN2  : session normale semestre 2 (activée manuellement par l'admin)
      - TOUT : mode rattrapage — tout est ouvert

    Règles métier :
      - SN1 ne peut être activée que si CC1 a été ouverte
      - CC2 s'ouvre automatiquement après clôture de SN1
      - SN2 ne peut être activée que si CC2 a été ouverte
      - TOUT outrepasse toutes les règles (cas de rattrapage)
      - Une seule période active à la fois (sauf TOUT)
    """

    SECTION_CHOICES = (
        ('CC1', 'Contrôle Continu S1'),
        ('SN1', 'Session Normale S1'),
        ('CC2', 'Contrôle Continu S2'),
        ('SN2', 'Session Normale S2'),
        ('TOUT', 'Tout activer (Rattrapage)'),
    )

    annee_academique = models.CharField(max_length=20, default='2024-2025')
    section = models.CharField(max_length=10, choices=SECTION_CHOICES)

    date_debut = models.DateTimeField()
    date_fin = models.DateTimeField()

    is_active = models.BooleanField(default=False)
    ouvert_par = models.ForeignKey(
        Administrateur,
        on_delete=models.SET_NULL,
        null=True, blank=True,
        related_name='periodes_creees'
    )

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'periodes_saisie'
        verbose_name = 'Période de Saisie'
        verbose_name_plural = 'Périodes de Saisie'
        ordering = ['-date_debut']

    def __str__(self):
        statut = "Active" if self.is_active else "Inactive"
        return f"{self.section} — {self.annee_academique} ({statut})"

    @property
    def est_en_cours(self):
        """Vérifie si la période est active ET dans l'intervalle de dates"""
        now = timezone.now()
        return self.is_active and self.date_debut <= now <= self.date_fin

    @classmethod
    def sections_ouvertes(cls, annee_academique='2024-2025'):
        """
        Retourne la liste des sections actuellement ouvertes.
        Ex: ['CC1'] ou ['CC1', 'SN1'] ou ['CC1','SN1','CC2','SN2'] si TOUT
        """
        now = timezone.now()
        periodes = cls.objects.filter(
            annee_academique=annee_academique,
            is_active=True,
            date_debut__lte=now,
            date_fin__gte=now,
        )

        sections = set()
        for p in periodes:
            if p.section == 'TOUT':
                return ['CC1', 'SN1', 'CC2', 'SN2']
            sections.add(p.section)

        # Règle : quand SN1 est ouverte, CC1 reste aussi éditable
        if 'SN1' in sections:
            sections.add('CC1')

        # Règle : quand SN2 est ouverte, CC2 reste aussi éditable
        if 'SN2' in sections:
            sections.add('CC2')

        return list(sections)

    @classmethod
    def peut_activer_section(cls, section, annee_academique='2024-2025'):
        """
        Vérifie si une section peut être activée selon les règles métier.
        Retourne (True, None) ou (False, "message d'erreur")
        """
        if section == 'CC1':
            return True, None

        if section == 'SN1':
            # SN1 nécessite que CC1 ait existé
            cc1_existe = cls.objects.filter(
                annee_academique=annee_academique,
                section='CC1'
            ).exists()
            if not cc1_existe:
                return False, "CC1 doit être créée avant d'activer SN1."
            return True, None

        if section == 'CC2':
            # CC2 nécessite que SN1 ait existé
            sn1_existe = cls.objects.filter(
                annee_academique=annee_academique,
                section='SN1'
            ).exists()
            if not sn1_existe:
                return False, "SN1 doit être créée avant d'activer CC2."
            return True, None

        if section == 'SN2':
            # SN2 nécessite que CC2 ait existé
            cc2_existe = cls.objects.filter(
                annee_academique=annee_academique,
                section='CC2'
            ).exists()
            if not cc2_existe:
                return False, "CC2 doit être créée avant d'activer SN2."
            return True, None

        if section == 'TOUT':
            return True, None

        return False, "Section inconnue."


# ============================================================
# NOTES — restructurées avec CC1/SN1/CC2/SN2
# ============================================================
class Note(models.Model):
    """Notes des étudiants — une ligne par étudiant/matière/année"""
    etudiant = models.ForeignKey(Etudiant, on_delete=models.CASCADE, related_name='notes')
    matiere = models.ForeignKey(Matiere, on_delete=models.CASCADE, related_name='notes')
    enseignant = models.ForeignKey(Enseignant, on_delete=models.CASCADE, related_name='notes_saisies')

    annee_academique = models.CharField(max_length=20, default='2024-2025')

    # Semestre 1
    note_cc1 = models.DecimalField(
        max_digits=4, decimal_places=2, null=True, blank=True,
        validators=[MinValueValidator(0), MaxValueValidator(20)],
        verbose_name='Note CC1'
    )
    note_sn1 = models.DecimalField(
        max_digits=4, decimal_places=2, null=True, blank=True,
        validators=[MinValueValidator(0), MaxValueValidator(20)],
        verbose_name='Note SN1'
    )

    # Semestre 2
    note_cc2 = models.DecimalField(
        max_digits=4, decimal_places=2, null=True, blank=True,
        validators=[MinValueValidator(0), MaxValueValidator(20)],
        verbose_name='Note CC2'
    )
    note_sn2 = models.DecimalField(
        max_digits=4, decimal_places=2, null=True, blank=True,
        validators=[MinValueValidator(0), MaxValueValidator(20)],
        verbose_name='Note SN2'
    )

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'notes'
        verbose_name = 'Note'
        verbose_name_plural = 'Notes'
        # Une seule ligne par étudiant/matière/année (plus de semestre séparé)
        unique_together = ['etudiant', 'matiere', 'annee_academique']
        ordering = ['-created_at']

    def __str__(self):
        return (
            f"{self.etudiant} - {self.matiere} "
            f"(CC1:{self.note_cc1}, SN1:{self.note_sn1}, "
            f"CC2:{self.note_cc2}, SN2:{self.note_sn2})"
        )

    @property
    def moyenne_s1(self):
        """Moyenne du semestre 1"""
        if self.note_cc1 is not None and self.note_sn1 is not None:
            return round((float(self.note_cc1) + float(self.note_sn1)) / 2, 2)
        return None

    @property
    def moyenne_s2(self):
        """Moyenne du semestre 2"""
        if self.note_cc2 is not None and self.note_sn2 is not None:
            return round((float(self.note_cc2) + float(self.note_sn2)) / 2, 2)
        return None

    @property
    def moyenne_annuelle(self):
        """Moyenne des deux semestres"""
        if self.moyenne_s1 is not None and self.moyenne_s2 is not None:
            return round((self.moyenne_s1 + self.moyenne_s2) / 2, 2)
        return None


# OTP VERIFICATION
class OTPVerification(models.Model):
    """Code OTP pour vérification email"""
    email = models.EmailField()
    code = models.CharField(max_length=6)
    is_used = models.BooleanField(default=False)
    expires_at = models.DateTimeField()

    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = 'otp_verifications'
        verbose_name = 'OTP Verification'
        verbose_name_plural = 'OTP Verifications'
        ordering = ['-created_at']

    def __str__(self):
        return f"{self.email} - {self.code} ({'Utilisé' if self.is_used else 'Actif'})"


class EmploiDuTemps(models.Model):
    """Créneau horaire d'un cours (planning hebdomadaire)"""

    JOUR_CHOICES = (
        ('LUN', 'Lundi'),
        ('MAR', 'Mardi'),
        ('MER', 'Mercredi'),
        ('JEU', 'Jeudi'),
        ('VEN', 'Vendredi'),
        ('SAM', 'Samedi'),
    )

    niveau = models.ForeignKey(Niveau, on_delete=models.CASCADE, related_name='creneaux')
    filiere = models.ForeignKey(Filiere, on_delete=models.CASCADE, related_name='creneaux')
    specialite = models.ForeignKey(Specialite, on_delete=models.CASCADE, related_name='creneaux')
    matiere = models.ForeignKey(Matiere, on_delete=models.CASCADE, related_name='creneaux')
    enseignant = models.ForeignKey(Enseignant, on_delete=models.CASCADE, related_name='creneaux')

    jour = models.CharField(max_length=3, choices=JOUR_CHOICES)
    heure_debut = models.TimeField()
    heure_fin = models.TimeField()
    salle = models.CharField(max_length=50, blank=True, null=True)

    annee_academique = models.CharField(max_length=20, default='2024-2025')

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'emplois_du_temps'
        verbose_name = 'Créneau'
        verbose_name_plural = 'Emploi du temps'
        ordering = ['jour', 'heure_debut']

    def __str__(self):
        return f"{self.matiere} — {self.get_jour_display()} {self.heure_debut}-{self.heure_fin}"

    def clean(self):
        from django.core.exceptions import ValidationError
        if self.heure_fin <= self.heure_debut:
            raise ValidationError("L'heure de fin doit être après l'heure de début.")