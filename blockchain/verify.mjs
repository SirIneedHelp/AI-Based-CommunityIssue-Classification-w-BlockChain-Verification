import { readFileSync } from "fs";
import { ethers } from "ethers";

// Ganache RPC
const RPC_URL = "http://127.0.0.1:7545";

// Deployer private key (Ganache)
const PRIVATE_KEY =
  "0x3c451a1481913a3563a836534e59e0128332f7a0463fb4e81066ec512a600dfd";

// Your deployed contract address
const CONTRACT_ADDRESS = "0x5143B8DF60463ba6CC19e382302298a3953C6f57";

// ABI from Hardhat artifact
const artifactPath = "./artifacts/contracts/Verification.sol/Verification.json";

function usage() {
  console.log(
    'Usage:\n  node verify.mjs <reportId> <dataHashHex> <category> <modelVersion>\n\n' +
      'Example:\n  node verify.mjs 12 0x' +
      'a'.repeat(64) +
      ' "Garbage" "v1"\n\n' +
      "Notes:\n" +
      "- reportId must be a number\n" +
      "- dataHashHex must be 0x + 64 hex chars (bytes32)\n"
  );
}

function isBytes32(hex) {
  return /^0x[0-9a-fA-F]{64}$/.test(hex);
}

async function main() {
  const args = process.argv.slice(2);
  if (args.length < 4) {
    usage();
    process.exit(1);
  }

  const reportId = Number(args[0]);
  const dataHashHex = args[1];
  const category = args[2];
  const modelVersion = args[3];

  if (!Number.isInteger(reportId) || reportId < 0) {
    console.error("❌ reportId must be a non-negative integer");
    process.exit(1);
  }

  if (!isBytes32(dataHashHex)) {
    console.error("❌ dataHashHex must be bytes32: 0x + 64 hex chars");
    process.exit(1);
  }

  const artifact = JSON.parse(readFileSync(artifactPath, "utf8"));
  const abi = artifact.abi;

  const provider = new ethers.JsonRpcProvider(RPC_URL);
  const wallet = new ethers.Wallet(PRIVATE_KEY, provider);

  const contract = new ethers.Contract(CONTRACT_ADDRESS, abi, wallet);

  console.log("Sending transaction...");
  const tx = await contract.recordVerification(
    reportId,
    dataHashHex,
    category,
    modelVersion
  );

  console.log("⏳ tx hash:", tx.hash);
  const receipt = await tx.wait();
  console.log("✅ confirmed in block:", receipt.blockNumber);
}

main().catch((e) => {
  console.error("❌ verify failed:", e);
  process.exit(1);
});
