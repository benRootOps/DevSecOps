from rest_framework import serializers
from .models import (
    CustomUser, Enseignant, Administrateur, Niveau, Filiere, Specialite,
    Matiere, Etudiant, Note, EnseignantNiveau,
    EnseignantSpecialite, EnseignantMatiere, OTPVerification, PeriodeSaisie,EmploiDuTemps
)
from django.utils import timezone
from datetime import timedelta
import random


# ============ Serializers de base ============

class NiveauSerializer(serializers.ModelSerializer):
    class Meta:
        model = Niveau
        fields = ['id', 'nom', 'description', 'ordre']


class FiliereSerializer(serializers.ModelSerializer):
    class Meta:
        model = Filiere
        fields = ['id', 'nom', 'description', 'code']


class SpecialiteSerializer(serializers.ModelSerializer):
    filiere_nom = serializers.CharField(source='filiere.nom', read_only=True)

    class Meta:
        model = Specialite
        fields = ['id', 'nom', 'filiere', 'filiere_nom', 'description']


class MatiereSerializer(serializers.ModelSerializer):
    niveau_nom = serializers.CharField(source='niveau.nom', read_only=True)
    filiere_nom = serializers.CharField(source='filiere.nom', read_only=True)

    class Meta:
        model = Matiere
        fields = ['id', 'nom', 'code', 'coefficient', 'niveau', 'niveau_nom', 'filiere', 'filiere_nom', 'description']


class EtudiantSerializer(serializers.ModelSerializer):
    niveau_nom = serializers.CharField(source='niveau.nom', read_only=True)
    filiere_nom = serializers.CharField(source='filiere.nom', read_only=True)
    specialite_nom = serializers.CharField(source='specialite.nom', read_only=True)

    class Meta:
        model = Etudiant
        fields = [
            'id', 'matricule', 'nom', 'prenom', 'email', 'telephone',
            'niveau', 'niveau_nom', 'filiere', 'filiere_nom',
            'specialite', 'specialite_nom', 'date_naissance', 'lieu_naissance'
        ]


# ============ Serializers pour l'inscription ============

class RegisterEnseignantSerializer(serializers.Serializer):
    nom = serializers.CharField(max_length=100)
    prenom = serializers.CharField(max_length=100)
    date_naissance = serializers.DateField(required=False, allow_null=True)
    lieu_naissance = serializers.CharField(max_length=150, required=False, allow_blank=True)
    email = serializers.EmailField()
    password = serializers.CharField(min_length=6, write_only=True)
    niveau = serializers.CharField()
    filiere = serializers.CharField()
    specialites = serializers.ListField(child=serializers.CharField())
    matieres = serializers.ListField(child=serializers.CharField())
    def validate_email(self, value):
        if CustomUser.objects.filter(email=value).exists():
            raise serializers.ValidationError("Un utilisateur avec cet email existe déjà.")
        return value

    def create(self, validated_data):
        from django.db import transaction

        niveau_nom = validated_data.pop('niveau')
        filiere_nom = validated_data.pop('filiere')
        specialites_noms = validated_data.pop('specialites')
        matieres_noms = validated_data.pop('matieres')
        password = validated_data.pop('password')

       
        try:
            niveau = Niveau.objects.get(nom=niveau_nom)
        except Niveau.DoesNotExist:
            raise serializers.ValidationError({'niveau': f"Niveau '{niveau_nom}' introuvable."})

        try:
            filiere = Filiere.objects.get(nom=filiere_nom)
        except Filiere.DoesNotExist:
            raise serializers.ValidationError({'filiere': f"Filière '{filiere_nom}' introuvable."})

        specialites = []
        for nom in specialites_noms:
            try:
                specialites.append(Specialite.objects.get(nom=nom))
            except Specialite.DoesNotExist:
                raise serializers.ValidationError({'specialites': f"Spécialité '{nom}' introuvable."})

        matieres = []
        for nom in matieres_noms:
            try:
                matieres.append(Matiere.objects.get(nom=nom))
            except Matiere.DoesNotExist:
                raise serializers.ValidationError({'matieres': f"Matière '{nom}' introuvable."})

        with transaction.atomic():
            user = CustomUser.objects.create_user(
                username=validated_data['email'],
                email=validated_data['email'],
                password=password,
                role='enseignant'
            )
            enseignant = Enseignant.objects.create(
                user=user,
                nom=validated_data['nom'],
                prenom=validated_data['prenom'],
                date_naissance=validated_data.get('date_naissance'),
                lieu_naissance=validated_data.get('lieu_naissance'),
                is_verified=False
            )
            EnseignantNiveau.objects.create(enseignant=enseignant, niveau=niveau, filiere=filiere)
            for specialite in specialites:
                EnseignantSpecialite.objects.create(enseignant=enseignant, specialite=specialite)
            for matiere in matieres:
                EnseignantMatiere.objects.create(enseignant=enseignant, matiere=matiere)
            return enseignant


class RegisterAdministrateurSerializer(serializers.Serializer):
    nom = serializers.CharField(max_length=100)
    prenom = serializers.CharField(max_length=100)
    date_naissance = serializers.DateField(required=False, allow_null=True)
    lieu_naissance = serializers.CharField(max_length=150, required=False, allow_blank=True)
    email = serializers.EmailField()
    password = serializers.CharField(min_length=6, write_only=True)

    def validate_email(self, value):
        if CustomUser.objects.filter(email=value).exists():
            raise serializers.ValidationError("Un utilisateur avec cet email existe déjà.")
        return value

    def create(self, validated_data):
        from django.db import transaction
        password = validated_data.pop('password')
        with transaction.atomic():
            user = CustomUser.objects.create_user(
                username=validated_data['email'],
                email=validated_data['email'],
                password=password,
                role='administrateur',
                is_staff=False
            )
            admin = Administrateur.objects.create(
                user=user,
                nom=validated_data['nom'],
                prenom=validated_data['prenom'],
                date_naissance=validated_data.get('date_naissance'),
                lieu_naissance=validated_data.get('lieu_naissance'),
                is_approved=False
            )
            return admin


# ============ Serializer OTP ============

class OTPRequestSerializer(serializers.Serializer):
    email = serializers.EmailField()

    def validate_email(self, value):
        try:
            user = CustomUser.objects.get(email=value)
            if not hasattr(user, 'enseignant_profile') and not hasattr(user, 'admin_profile'):
                raise serializers.ValidationError("Cet email n'est pas associé à un compte valide.")
        except CustomUser.DoesNotExist:
            raise serializers.ValidationError("Aucun compte trouvé avec cet email.")
        return value

    def create(self, validated_data):
        email = validated_data['email']
        code = ''.join([str(random.randint(0, 9)) for _ in range(6)])
        expires_at = timezone.now() + timedelta(minutes=10)
        otp = OTPVerification.objects.create(email=email, code=code, expires_at=expires_at)
        print(f"Code OTP pour {email}: {code}")
        return otp


class OTPVerifySerializer(serializers.Serializer):
    email = serializers.EmailField()
    code = serializers.CharField(max_length=6)

    def validate(self, data):
        email = data['email']
        code = data['code']
        print("=" *30)
        print("code reçus ", code)
        print("=" *30)
        try:
            otp = OTPVerification.objects.filter(
                email=email, code=code, is_used=False, expires_at__gt=timezone.now()
            ).latest('created_at')
            otp.is_used = True
            otp.save()
            try:
                user = CustomUser.objects.get(email=email)
                if hasattr(user, 'enseignant_profile'):
                    enseignant = user.enseignant_profile
                    enseignant.is_verified = True
                    enseignant.is_active = True
                    enseignant.save()
                elif hasattr(user, 'admin_profile'):
                    print(f"✅ Email vérifié pour admin: {user.email}")
            except CustomUser.DoesNotExist:
                raise serializers.ValidationError("Utilisateur non trouvé.")
            print("Utilisateur non trouvé.")
            return data
        except OTPVerification.DoesNotExist:
            print("Code invalide ou expiré.")
            raise serializers.ValidationError("Code invalide ou expiré.")


# ============ Serializers Dashboard Enseignant ============

class EnseignantProfileSerializer(serializers.ModelSerializer):
    email = serializers.EmailField(source='user.email', read_only=True)
    username = serializers.CharField(source='user.username', read_only=True)

    class Meta:
        model = Enseignant
        fields = ['id', 'nom', 'prenom', 'email', 'username', 'date_naissance', 'lieu_naissance', 'is_verified']


class NoteSerializer(serializers.ModelSerializer):
    etudiant_nom = serializers.CharField(source='etudiant.nom', read_only=True)
    etudiant_prenom = serializers.CharField(source='etudiant.prenom', read_only=True)
    matiere_nom = serializers.CharField(source='matiere.nom', read_only=True)
    moyenne_s1 = serializers.SerializerMethodField()
    moyenne_s2 = serializers.SerializerMethodField()

    class Meta:
        model = Note
        fields = [
            'id', 'etudiant', 'etudiant_nom', 'etudiant_prenom',
            'matiere', 'matiere_nom',
            'note_cc1', 'note_sn1', 'note_cc2', 'note_sn2',
            'moyenne_s1', 'moyenne_s2',
            'annee_academique',
        ]

    def get_moyenne_s1(self, obj):
        return obj.moyenne_s1

    def get_moyenne_s2(self, obj):
        return obj.moyenne_s2


# ============ Serializer pour sauvegarder les notes ============

class SaveNotesSerializer(serializers.Serializer):
    """
    Sauvegarde des notes CC1/SN1/CC2/SN2.
    Seuls les champs des sections actuellement ouvertes sont écrits en base.
    """
    annee_academique = serializers.CharField(max_length=20, default='2024-2025')
    notes = serializers.ListField(child=serializers.DictField())

    def _valider_note(self, valeur, nom_champ):
        if valeur is not None:
            try:
                note = float(valeur)
                if not (0 <= note <= 20):
                    raise serializers.ValidationError(f"{nom_champ} doit être entre 0 et 20.")
            except (ValueError, TypeError):
                raise serializers.ValidationError(f"{nom_champ} invalide.")

    def validate_notes(self, value):
        for note_data in value:
            if 'student_id' not in note_data or 'matiere_id' not in note_data:
                raise serializers.ValidationError("student_id et matiere_id sont requis.")
            self._valider_note(note_data.get('note_cc1'), 'note_cc1')
            self._valider_note(note_data.get('note_sn1'), 'note_sn1')
            self._valider_note(note_data.get('note_cc2'), 'note_cc2')
            self._valider_note(note_data.get('note_sn2'), 'note_sn2')
        return value

    def create(self, validated_data):
        from django.db import transaction

        enseignant = self.context.get('enseignant')
        annee_academique = validated_data.get('annee_academique', '2024-2025')
        notes_data = validated_data['notes']

        # Sections ouvertes — on ne touche que celles autorisées
        sections_ouvertes = PeriodeSaisie.sections_ouvertes(annee_academique)

        saved_notes = []
        errors = []

        with transaction.atomic():
            for note_data in notes_data:
                etudiant_id = note_data['student_id']
                matiere_id = note_data['matiere_id']

                try:
                    etudiant = Etudiant.objects.get(id=etudiant_id)
                    matiere = Matiere.objects.get(id=matiere_id)

                    # Ne mettre à jour que les champs des sections ouvertes
                    defaults = {'enseignant': enseignant}

                    if 'CC1' in sections_ouvertes:
                        val = note_data.get('note_cc1')
                        defaults['note_cc1'] = float(val) if val is not None else None

                    if 'SN1' in sections_ouvertes:
                        val = note_data.get('note_sn1')
                        defaults['note_sn1'] = float(val) if val is not None else None

                    if 'CC2' in sections_ouvertes:
                        val = note_data.get('note_cc2')
                        defaults['note_cc2'] = float(val) if val is not None else None

                    if 'SN2' in sections_ouvertes:
                        val = note_data.get('note_sn2')
                        defaults['note_sn2'] = float(val) if val is not None else None

                    note, _ = Note.objects.update_or_create(
                        etudiant=etudiant,
                        matiere=matiere,
                        annee_academique=annee_academique,
                        defaults=defaults,
                    )
                    saved_notes.append(note)

                except Etudiant.DoesNotExist:
                    errors.append({'student_id': etudiant_id, 'error': 'Étudiant introuvable'})
                except Matiere.DoesNotExist:
                    errors.append({'matiere_id': matiere_id, 'error': 'Matière introuvable'})

        return {
            'saved_count': len(saved_notes),
            'notes': saved_notes,
            'errors': errors,
        }


# ============ Serializer Période de Saisie ============

class PeriodeSaisieSerializer(serializers.ModelSerializer):
    est_en_cours = serializers.SerializerMethodField()
    ouvert_par_nom = serializers.SerializerMethodField()
    section_label = serializers.CharField(source='get_section_display', read_only=True)

    class Meta:
        model = PeriodeSaisie
        fields = [
            'id', 'section', 'section_label', 'annee_academique',
            'date_debut', 'date_fin', 'is_active',
            'est_en_cours', 'ouvert_par_nom', 'created_at',
        ]

    def get_est_en_cours(self, obj):
        return obj.est_en_cours

    def get_ouvert_par_nom(self, obj):
        if obj.ouvert_par:
            return f"{obj.ouvert_par.prenom} {obj.ouvert_par.nom}"
        return None


class EmploiDuTempsSerializer(serializers.ModelSerializer):
    niveau_nom = serializers.CharField(source='niveau.nom', read_only=True)
    filiere_nom = serializers.CharField(source='filiere.nom', read_only=True)
    specialite_nom = serializers.CharField(source='specialite.nom', read_only=True)
    matiere_nom = serializers.CharField(source='matiere.nom', read_only=True)
    enseignant_nom = serializers.SerializerMethodField()
    jour_label = serializers.CharField(source='get_jour_display', read_only=True)

    class Meta:
        model = EmploiDuTemps
        fields = [
            'id', 'niveau', 'niveau_nom', 'filiere', 'filiere_nom',
            'specialite', 'specialite_nom', 'matiere', 'matiere_nom',
            'enseignant', 'enseignant_nom', 'jour', 'jour_label',
            'heure_debut', 'heure_fin', 'salle', 'annee_academique',
        ]

    def get_enseignant_nom(self, obj):
        return f"{obj.enseignant.prenom} {obj.enseignant.nom}"