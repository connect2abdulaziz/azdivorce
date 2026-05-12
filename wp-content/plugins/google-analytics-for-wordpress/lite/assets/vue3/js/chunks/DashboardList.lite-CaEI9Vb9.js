import { o as onMounted, a as openBlock, c as createElementBlock, b as createVNode, u as unref, d as useRouter } from "../custom-dashboard.js";
import { u as useFeatureGate, U as UpsellModal, a as useSampleData } from "./useSampleData-vhpTLOJT.js";
const _hoisted_1 = { class: "monsterinsights-dashboard-list" };
const _sfc_main = {
  __name: "DashboardList.lite",
  setup(__props) {
    const router = useRouter();
    const {
      shouldShowUpsell,
      upsellContent,
      hasSampleData,
      openUpsellModal,
      closeUpsellModal,
      enableSampleMode
    } = useFeatureGate("custom-dashboard");
    const { sampleData: sampleViewData, loadSampleData: loadSampleView } = useSampleData("custom-dashboard", "sample-view");
    onMounted(async () => {
      await loadSampleView();
      openUpsellModal();
    });
    async function handleSeeSample() {
      if (!sampleViewData.value) {
        await loadSampleView();
      }
      enableSampleMode();
      if (sampleViewData.value?.[0]?.id) {
        router.push({
          name: "dashboard-view",
          params: { id: sampleViewData.value[0].id }
        });
      }
    }
    return (_ctx, _cache) => {
      return openBlock(), createElementBlock("div", _hoisted_1, [
        createVNode(UpsellModal, {
          isOpen: unref(shouldShowUpsell),
          feature: "custom-dashboard",
          content: unref(upsellContent),
          showSampleButton: unref(hasSampleData),
          customImage: "sample-image-monsterinsights.png",
          onClose: unref(closeUpsellModal),
          onSeeSample: handleSeeSample
        }, null, 8, ["isOpen", "content", "showSampleButton", "onClose"])
      ]);
    };
  }
};
export {
  _sfc_main as default
};
