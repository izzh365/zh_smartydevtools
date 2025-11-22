document.addEventListener("DOMContentLoaded", function() {
    // 确保DOM元素存在后再执行
    if (!document.getElementById("smarty-structure-visualizer")) {
        return;
    }

    // 获取元素
    var btn = document.getElementById("smarty-structure-btn");
    var modal = document.getElementById("smarty-structure-modal");
    var closeBtn = document.querySelector(".smarty-modal-close");
    var tablinks = document.querySelectorAll(".tablink");
    var tabcontents = document.querySelectorAll(".tabcontent");
    var modalContent = document.querySelector(".smarty-modal-content");
    
    // 打开模态框
    btn.addEventListener("click", function() {
        modal.style.display = "block";
        // 防止背景滚动
        document.body.style.overflow = "hidden";
        // 添加打开类以支持动画
        modal.classList.add("open");
    });
    
    // 关闭模态框
    if (closeBtn) {
        closeBtn.addEventListener("click", function() {
            modal.style.display = "none";
            // 恢复背景滚动
            document.body.style.overflow = "auto";
            // 移除打开类
            modal.classList.remove("open");
        });
    }
    
    // 点击外部关闭模态框
    window.addEventListener("click", function(event) {
        if (event.target == modal || (event.target == modalContent && !modalContent.contains(event.target))) {
            modal.style.display = "none";
            // 恢复背景滚动
            document.body.style.overflow = "auto";
            // 移除打开类
            modal.classList.remove("open");
        }
    });
    
    // ESC键关闭模态框
    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape" && modal.style.display === "block") {
            modal.style.display = "none";
            // 恢复背景滚动
            document.body.style.overflow = "auto";
            // 移除打开类
            modal.classList.remove("open");
        }
    });
    
    // 标签切换
    tablinks.forEach(function(tablink) {
        tablink.addEventListener("click", function() {
            var tabName = this.getAttribute("data-tab");
            
            // 移除所有active类
            tablinks.forEach(function(tl) {
                tl.classList.remove("active");
            });
            tabcontents.forEach(function(tc) {
                tc.classList.remove("active");
            });
            
            // 添加active类到当前标签
            this.classList.add("active");
            document.getElementById(tabName).classList.add("active");
        });
    });
    
    // 树形结构折叠/展开功能
    document.addEventListener("click", function(e) {
        if (e.target.classList.contains("toggle-icon")) {
            var parentLi = e.target.closest("li");
            if (parentLi) {
                parentLi.classList.toggle("collapsed");
                e.target.textContent = parentLi.classList.contains("collapsed") ? "⊕" : "⊖";
            }
        }
    });
    
    // 支持键盘导航
    if (modal) {
        var focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var firstFocusableElement = focusableElements[0];  
        var lastFocusableElement = focusableElements[focusableElements.length - 1];

        // 在模态框内限制焦点
        modal.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusableElement) {
                        lastFocusableElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastFocusableElement) {
                        firstFocusableElement.focus();
                        e.preventDefault();
                    }
                }
            }
        });
    }
    
    // 监听窗口大小变化，确保模态框在窗口大小变化时保持居中
    window.addEventListener("resize", function() {
        if (modal && modal.style.display === "block") {
            modal.style.display = "flex";
            modal.style.alignItems = "center";
        }
    });
});