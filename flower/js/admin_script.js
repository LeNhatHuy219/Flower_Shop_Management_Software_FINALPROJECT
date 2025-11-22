let navbar = document.querySelector('.header .flex .navbar');
let userBox = document.querySelector('.header .flex .account-box');

document.querySelector('#menu-btn').onclick = () =>{
   navbar.classList.toggle('active');
   userBox.classList.remove('active');
}

document.querySelector('#user-btn').onclick = () =>{
   userBox.classList.toggle('active'); 
   navbar.classList.remove('active');
}

window.onscroll = () =>{
   navbar.classList.remove('active');
   userBox.classList.remove('active');
}

// (MỚI) Lấy sidebar và nút menu
let sidebar = document.querySelector('.sidebar');
let menuBtn = document.querySelector('#menu-btn-mobile');

// (MỚI) Xử lý bật/tắt sidebar trên mobile
menuBtn.onclick = () => {
    sidebar.classList.toggle('active');
}

// (MỚI) Đóng sidebar khi cuộn
window.onscroll = () => {
    sidebar.classList.remove('active');
}

// (SỬA) Xóa logic cũ của #user-btn vì nó không còn tồn tại
// document.querySelector('#user-btn').onclick = () =>{
//    document.querySelector('.header .flex .account-box').classList.toggle('active');
// }

// (SỬA) Xóa logic cũ của #menu-btn
// let navbar = document.querySelector('.header .flex .navbar');
// document.querySelector('#menu-btn').onclick = () =>{
//    navbar.classList.toggle('active');
//    profile.classList.remove('active');
// }
