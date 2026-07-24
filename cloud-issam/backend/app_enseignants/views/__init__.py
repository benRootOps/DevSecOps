
from .auth_views import (
    register_enseignant,
    register_administrateur,
    request_otp,
    verify_otp,
    login,
    logout
)

from .enseignant_views import (
    enseignant_profile,
    enseignant_niveaux,
    enseignant_filieres,
    enseignant_specialites,
    enseignant_matieres,
    students_list,
    get_resultats,
    notes_list,
    save_notes,
    enseignant_affectations,
    ajouter_niveau_filiere,
    ajouter_specialite,
    ajouter_matiere,
    supprimer_niveau_filiere,
    supprimer_specialite,
    supprimer_matiere,
    mon_planning,
)

from .form_views import (
    get_form_data,
    get_matieres_filtered
)

from .viewsets import (
    NiveauViewSet,
    FiliereViewSet,
    SpecialiteViewSet,
    MatiereViewSet,
    EtudiantViewSet,
    NoteViewSet
)

__all__ = [
    # Auth
    'register_enseignant',
    'register_administrateur',
    'request_otp',
    'verify_otp',
    'login',
    'logout',

    # Enseignant Dashboard
    'enseignant_profile',
    'enseignant_niveaux',
    'enseignant_filieres',
    'enseignant_specialites',
    'enseignant_matieres',
    'students_list',
    'notes_list',
    'save_notes',

    # Formulaire d'inscription
    'get_form_data',
    'get_matieres_filtered',

    # ViewSets
    'NiveauViewSet',
    'FiliereViewSet',
    'SpecialiteViewSet',
    'MatiereViewSet',
    'EtudiantViewSet',
    'NoteViewSet',
]