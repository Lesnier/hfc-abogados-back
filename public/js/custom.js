function addRoleToName(rol = 'Invitado') {
    const elementOfName = document.querySelector(".app-container .side-menu .panel.widget h4");
    const smallChild = document.createElement('small');
    smallChild.textContent = rol;
    smallChild.className = 'dashboard-user-role';
    elementOfName.after(smallChild);
}

addRoleToName(window.voyagerGlobalData.userRole);
